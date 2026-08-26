<?php

namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Arr;
use BlueFission\Connections\Database\MySQLLink;
use BlueFission\Collections\ICollection;
use BlueFission\BlueCore\Contracts\IAddOnLifecycleManager;
use BlueFission\Data\FileSystem;
use BlueFission\Data\Storage\Storage;
use BlueFission\Func;
use BlueFission\Net\HTTP;
use BlueFission\Flag;
use BlueFission\Security\Hash;
use BlueFission\BlueCore\Registration\RegistrationPlan;
use BlueFission\Services\Application;
use BlueFission\Services\Service;
use BlueFission\Utils\Loader;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use BlueFission\BlueCore\Domain\AddOn\Models\AddOnModel;
use BlueFission\BlueCore\Domain\AddOn\AddOn;
use BlueFission\Str;
use BlueFission\System\System;
use BlueFission\Val;

class AddOnManager extends Service implements IAddOnLifecycleManager
{
    private $_loader;
    protected $_model;
    private array $_loadedAddOns = [];
    private array $_registeredAddOns = [];

    public function __construct(MySQLLink $link)
    {
        $link->open();
        $this->_model = new AddOnModel();
        $this->_loader = Loader::instance();
        $this->autoload();
        parent::__construct();
    }

    public function install($name, bool $installDependencies = false): array
    {
        $data = $this->getAddOnData($name);
        $addOnPath = $this->addOnPath($data->name);
        $result = $this->lifecycleResult('install', $data->name);

        if ($this->isInstalled($data->name)) {
            $result['stage'] = 'complete';
            $result['messages'][] = 'Add-on is already installed.';
            $result['modelStatus'] = $this->_model->status();
            $result['query'] = $this->_model->query();

            return $result;
        }

        $dependencyCommands = $this->dependencyCommands($data->libraries, 'require');
        $result['dependencies'] = $dependencyCommands;

        if (Arr::isNotEmpty($dependencyCommands)) {
            if ($installDependencies) {
                $result['messages'][] = $this->runDependencyCommands($dependencyCommands);
            } else {
                $result['messages'][] = 'Dependency installation skipped; run the listed commands explicitly if required.';
            }
        }

        $addon = new AddOn;
        $addon->assign($data);
        $addon->path = $addOnPath;

        $hookPreflight = $this->resolvePrimaryFile($addon);
        if (Flag::isFalse($hookPreflight['ok'])) {
            $hookResult = $this->callHook($addon, 'install');
            $result['hooks'][] = $hookResult;

            return $this->withHookFailure($result, $hookResult, 'install');
        }

        $datasource = $this->datasourceManager();
        $datasource->setDeltaDirectory($addon->path . DIRECTORY_SEPARATOR . 'datasources' . DIRECTORY_SEPARATOR . 'structure' . DIRECTORY_SEPARATOR);
        $datasource->setGeneratorDirectory($addon->path . DIRECTORY_SEPARATOR . 'datasources' . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR);
        $migrationResult = $datasource->runMigrations($data->name);
        $result['migrations'] = $migrationResult;
        if (Flag::isFalse(Arr::getPath($migrationResult, 'ok', false))) {
            $result['ok'] = false;
            $result['stage'] = 'datasource';
            $result['nextAction'] = 'retry_install';
            $result['error'] = Arr::getPath($migrationResult, 'error', 'Add-on migration failed.');
            $result['messages'][] = $result['error'];

            return $result;
        }

		$populationResult = $datasource->populate();
		$result['population'] = $populationResult;
		if (Flag::isFalse(Arr::getPath($populationResult, 'ok', false))) {
			$result['ok'] = false;
			$result['stage'] = 'datasource';
			$result['nextAction'] = 'review_population_failure';
			$result['error'] = Arr::getPath($populationResult, 'error', 'Add-on population failed.');
			$result['messages'][] = $result['error'];

			return $result;
		}

        $hookResult = $this->callHook($addon, 'install');
        if (Arr::is($hookResult)) {
            $result['hooks'][] = $hookResult;
        }
        if (Flag::isFalse(Arr::getPath($hookResult, 'ok', false))) {
            return $this->withHookFailure($result, $hookResult, 'install');
        }

        $this->_model->write($addon);
        $result['changed'] = true;
        $result['stage'] = 'complete';
        $result['modelStatus'] = $this->_model->status();
        $result['query'] = $this->_model->query();

        return $result;
    }

    public function installAll(bool $installDependencies = false): array
    {
        $addOns = $this->showAllAddOns();
        $results = [];
        foreach ($addOns as $addOn) {
            try {
                $results[] = $this->install($addOn->name, $installDependencies);
            } catch (\Throwable $exception) {
                $results[] = $this->failedLifecycleResult('install', (string)$addOn->name, $exception);
            }
        }

        return $this->lifecycleBatchResult('install_all', $results);
    }

    public function uninstall($addOnId, bool $removeDependencies = false): array
    {
        $addon = $this->getAddOnById($addOnId);
        $result = $this->lifecycleResult('uninstall', $addon->name);

        try {
            $result['stage'] = 'hook';
            $hookResult = $this->callHook($addon, 'uninstall');
            if (Arr::is($hookResult)) {
                $result['hooks'][] = $hookResult;
            }
            if (Flag::isFalse(Arr::getPath($hookResult, 'ok', false))) {
                return $this->withHookFailure($result, $hookResult, 'uninstall');
            }

            $result['stage'] = 'definition';
            $data = $this->getAddOnData($addon->name);
            $dependencyCommands = $this->dependencyCommands($data->libraries, 'remove');
            $result['dependencies'] = $dependencyCommands;

            if (Arr::isNotEmpty($dependencyCommands)) {
                if ($removeDependencies) {
                    $result['stage'] = 'dependencies';
                    $result['messages'][] = $this->runDependencyCommands($dependencyCommands);
                } else {
                    $result['messages'][] = 'Dependency removal skipped; run the listed commands explicitly if required.';
                }
            }

            $result['stage'] = 'datasource';
            $datasource = $this->datasourceManager();
            $datasource->setDeltaDirectory($addon->path . DIRECTORY_SEPARATOR . 'datasources' . DIRECTORY_SEPARATOR . 'structure' . DIRECTORY_SEPARATOR);
            $datasource->setGeneratorDirectory($addon->path . DIRECTORY_SEPARATOR . 'datasources' . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR);
            $result['messages'][] = $this->captureOutput(fn() => $datasource->revertBatch($addon->name));

            $result['stage'] = 'registration';
            $this->_model->delete(['addon_id' => $addOnId]);
            if ($this->_model->status() !== Storage::STATUS_SUCCESS) {
                throw new \RuntimeException('Add-on registration could not be removed.');
            }

            $result['changed'] = true;
            $result['stage'] = 'complete';
        } catch (\Throwable $exception) {
            $result['ok'] = false;
            $result['nextAction'] = 'retry_uninstall';
            $result['error'] = $exception->getMessage();
            $result['messages'][] = $exception->getMessage();
        }

        $result['modelStatus'] = $this->_model->status();

        return $result;
    }

    public function activate($addOnId): array
    {
        return $this->updateActivation($addOnId, true);
    }

    public function activateAll(): array
    {
        return $this->updateAllActivation(true);
    }

    public function deactivateAll(): array
    {
        return $this->updateAllActivation(false);
    }

    private function updateAllActivation(bool $active): array
    {
        $addOns = $this->installedAddOns();
        $results = Arr::make([]);
        $action = $active ? 'activate' : 'deactivate';

        foreach ($addOns as $addOn) {
            $record = Arr::make($addOn);
            if (Flag::parseBool($record->get('is_active')) === $active) {
                continue;
            }

            $addOnId = $record->get('addon_id');
            if (Val::isEmpty($addOnId)) {
                $failure = $this->failedLifecycleResult(
                    $action,
                    '',
                    new \UnexpectedValueException('Persisted add-on row is missing addon_id.')
                );
                $failure['record'] = $record->toArray();
                $results->push($failure);
                continue;
            }

            $result = $active
                ? $this->activate($addOnId)
                : $this->deactivate($addOnId);
            $result['record'] = $record->toArray();
            $results->push($result);
        }

        return $this->lifecycleBatchResult("{$action}_all", $results->toArray());
    }

    public function deactivate($addOnId): array
    {
        return $this->updateActivation($addOnId, false);
    }

    private function updateActivation($addOnId, bool $active): array
    {
        $action = $active ? 'activate' : 'deactivate';
        $this->_model->write([
            'addon_id' => $addOnId,
            'is_active' => $active ? 1 : 0,
        ]);

        $result = $this->lifecycleResult(
            $action,
            (string)$addOnId,
            $this->_model->status(),
            $this->_model->query()
        );

        if (Flag::isFalse($result['ok'])) {
            return $result;
        }

        $this->_model->clear()->read(['addon_id' => $addOnId]);
        $record = Arr::make((array)$this->_model->data());
        $verified = $this->_model->status() === Storage::STATUS_SUCCESS
            && $record->hasKey('is_active')
            && Flag::parseBool($record->get('is_active')) === $active;

        $result['verification'] = Arr::make([
            'ok' => $verified,
            'is_active' => $record->get('is_active'),
            'modelStatus' => $this->_model->status(),
        ])->toArray();

        if (Flag::isFalse($verified)) {
            $message = 'Add-on activation state was not persisted.';
            $result['ok'] = false;
            $result['changed'] = false;
            $result['stage'] = 'verification';
            $result['nextAction'] = "retry_{$action}";
            $result['error'] = $message;
            $result['messages'] = [$message];
        }

        return $result;
    }

    public function migrate($addOnId): array
    {
        $addOn = $this->getAddOnById($addOnId);
        $result = $this->lifecycleResult('migrate', (string)$addOn->name);
        $result['stage'] = 'datasource';

        try {
            $datasource = $this->datasourceManager();
            $datasource->setDeltaDirectory(
                $addOn->path . DIRECTORY_SEPARATOR . 'datasources' . DIRECTORY_SEPARATOR . 'structure' . DIRECTORY_SEPARATOR
            );
            $datasource->setGeneratorDirectory(
                $addOn->path . DIRECTORY_SEPARATOR . 'datasources' . DIRECTORY_SEPARATOR . 'generator' . DIRECTORY_SEPARATOR
            );
            $migrationResult = $datasource->runMigrations($addOn->name);
            $result['migrations'] = $migrationResult;
            $result['ok'] = Flag::parseBool(Arr::getPath($migrationResult, 'ok', false));
            $result['changed'] = Flag::parseBool(Arr::getPath($migrationResult, 'changed', false));
            $result['stage'] = $result['ok'] ? 'complete' : 'datasource';
            $result['nextAction'] = $result['ok'] ? null : 'retry_migrate';
            $result['error'] = Arr::getPath($migrationResult, 'error');
            if (Val::isNotEmpty($result['error'])) {
                $result['messages'][] = $result['error'];
            }
        } catch (\Throwable $exception) {
            $result['ok'] = false;
            $result['nextAction'] = 'retry_migrate';
            $result['error'] = $exception->getMessage();
            $result['messages'][] = $exception->getMessage();
        }

        return $result;
    }

    public function showAllAddOns()
    {
        $addonsPath = resolve_path('addons');
        if (!is_dir($addonsPath)) {
            return [];
        }

        $addOns = Arr::make(scandir($addonsPath) ?: [])
            ->diff(['.', '..'])
            ->values()
            ->toArray();
        $list = [];
        foreach ($addOns as $addOn) {
            try {
                $data = $this->getAddOnData($addOn);
            } catch (\Throwable $exception) {
                continue;
            }

            $model = new AddOn;
            $model->name = $data->name ?? $addOn;
            $model->description = Str::truncate($data->description ?? "");
            $model->path = $this->addOnPath($addOn);
            $model->primary_file = 'main.php';
            // $addOn = $model;
            $list[$data->name] = $model;
        }

        return $list;
    }

    public function loadActivatedAddOns(
        ?Application $application = null,
        ?RegistrationPlan $plan = null
    ): array
    {
        if (($application === null) !== ($plan === null)) {
            throw new \InvalidArgumentException(
                'Application and registration plan must be provided together.'
            );
        }

        $addOns = $this->_model->getActivatedAddOns();
        $results = Arr::make([]);

        foreach ($addOns as $addOn) {
            $object = new AddOn;
            $object->assign($addOn);
            $load = $this->loadAddOn($object);

            if (Flag::isFalse($load['ok']) || !Func::isCallable($load['value'] ?? null)) {
                $load['registration'] = $this->registrationResult(
                    Flag::isFalse($load['ok']) ? 'load_failed' : 'not_applicable'
                );
                $results->push($load);
                continue;
            }

            if ($application === null || $plan === null) {
                $load['registration'] = $this->registrationResult('pending');
                $results->push($load);
                continue;
            }

            $key = $this->addOnRuntimeKey($object, $load['path']);
            if (Arr::hasKey($this->_registeredAddOns, $key)) {
                $load['registration'] = $this->registrationResult('already_registered');
                $results->push($load);
                continue;
            }

            try {
                Func::make($load['value'])->call($application, $plan);
                $this->_registeredAddOns[$key] = true;
                $load['registration'] = $this->registrationResult('registered', true);
            } catch (\Throwable $exception) {
                $load['ok'] = false;
                $load['stage'] = 'registration';
                $load['error'] = $exception->getMessage();
                $load['exception'] = $exception::class;
                $load['registration'] = $this->registrationResult(
                    'failed',
                    false,
                    $exception
                );
            }

            $results->push($load);
        }

        return $results->toArray();
    }

    public function loadActivatedContributions(string $name): array
    {
        $name = Str::lower(Str::trim($name));
        if (Val::isEmpty($name) || !Str::matchPattern($name, '/^[a-z0-9][a-z0-9._-]*$/')) {
            throw new \InvalidArgumentException('Contribution names must be safe file identifiers.');
        }

        $entries = Arr::make([]);
        foreach ($this->_model->getActivatedAddOns() as $record) {
            $addOn = new AddOn;
            $addOn->assign($record);
            $entries->push($this->loadContribution($addOn, $name));
        }

        $failed = $entries
            ->filter(fn ($entry) => Str::match((string)Arr::getPath($entry, 'status'), 'failed'))
            ->values();
        $loaded = $entries
            ->filter(fn ($entry) => Str::match((string)Arr::getPath($entry, 'status'), 'loaded'))
            ->values();
        $missing = $entries
            ->filter(fn ($entry) => Str::match((string)Arr::getPath($entry, 'status'), 'missing'))
            ->values();
        $snapshot = $entries->toArray();

        return Arr::make([
            'ok' => Arr::isEmpty($failed->toArray()),
            'name' => $name,
            'revision' => Hash::value(HTTP::jsonEncode($snapshot), 'sha256'),
            'total' => Arr::size($snapshot),
            'loaded' => $loaded->count(),
            'missing' => $missing->count(),
            'failed' => $failed->count(),
            'results' => $snapshot,
        ])->toArray();
    }

    protected function loadContribution(AddOn $addOn, string $name): array
    {
        $resolution = $this->resolveContributionFile($addOn, $name);
        $entry = Arr::make([
            'ok' => true,
            'addonId' => $addOn->addon_id,
            'addon' => $addOn->name,
            'name' => $name,
            'status' => $resolution['status'],
            'path' => $resolution['path'],
            'attempted' => $resolution['attempted'],
            'data' => [],
            'error' => $resolution['error'],
            'exception' => null,
        ]);

        if (Str::match($resolution['status'], 'missing')) {
            return $entry->toArray();
        }

        if (Flag::isFalse($resolution['ok'])) {
            $entry->set('ok', false);
            $entry->set('status', 'failed');

            return $entry->toArray();
        }

        try {
            $data = $this->includeContribution($resolution['path']);
            if (!Arr::is($data) || Flag::isFalse($this->isPassiveContribution($data))) {
                throw new \UnexpectedValueException(
                    'Contribution files must return arrays containing only passive data.'
                );
            }

            $entry->set('status', 'loaded');
            $entry->set('data', Arr::make($data)->toArray());
        } catch (\Throwable $exception) {
            $entry->set('ok', false);
            $entry->set('status', 'failed');
            $entry->set('error', $exception->getMessage());
            $entry->set('exception', $exception::class);
        }

        return $entry->toArray();
    }

    protected function includeContribution(string $path): mixed
    {
        return include($path);
    }

    private function resolveContributionFile(AddOn $addOn, string $name): array
    {
        $addOnName = $this->normalizeAddOnIdentifier($addOn->name, 'name');
        $relative = 'mapping' . DIRECTORY_SEPARATOR . $name . '.php';
        $canonicalRoot = Path::normalize(resolve_path('addons' . DIRECTORY_SEPARATOR . $addOnName));
        $configuredRoot = Path::normalize((string)$addOn->path);
        $candidates = Arr::make([]);

        if (Val::isNotEmpty($configuredRoot)) {
            $candidates->push(Path::normalize($configuredRoot . DIRECTORY_SEPARATOR . $relative));
        }

        $canonical = Path::normalize($canonicalRoot . DIRECTORY_SEPARATOR . $relative);
        if (Val::isEmpty($configuredRoot) || Flag::isFalse($candidates->has($canonical))) {
            $candidates->push($canonical);
        }

        foreach ($candidates as $candidate) {
            if (!(new File())->isReachable($candidate)) {
                continue;
            }

            if (Flag::isFalse($this->primaryFileIsContained($candidate, $canonicalRoot))) {
                return Arr::make([
                    'ok' => false,
                    'status' => 'unsafe',
                    'path' => null,
                    'attempted' => $candidates->toArray(),
                    'error' => 'Contribution file escapes its configured add-on root.',
                ])->toArray();
            }

            return Arr::make([
                'ok' => true,
                'status' => 'resolved',
                'path' => Path::normalize(realpath($candidate)),
                'attempted' => $candidates->toArray(),
                'error' => null,
            ])->toArray();
        }

        return Arr::make([
            'ok' => true,
            'status' => 'missing',
            'path' => null,
            'attempted' => $candidates->toArray(),
            'error' => null,
        ])->toArray();
    }

    private function isPassiveContribution(mixed $value): bool
    {
        if (Func::isCallable($value) || is_object($value) || is_resource($value)) {
            return false;
        }

        if (!Arr::is($value)) {
            return true;
        }

        foreach ($value as $item) {
            if (Flag::isFalse($this->isPassiveContribution($item))) {
                return false;
            }
        }

        return true;
    }

    protected function loadAddOn(AddOn $addOn): array
    {
        $resolution = $this->resolvePrimaryFile($addOn);
        if (Flag::isFalse($resolution['ok'])) {
            return $resolution;
        }

        $key = $this->addOnRuntimeKey($addOn, $resolution['path']);
        if (Arr::hasKey($this->_loadedAddOns, $key)) {
            $loaded = $this->_loadedAddOns[$key];
            $loaded['status'] = 'already_loaded';
            $loaded['changed'] = false;

            return $loaded;
        }

        $resolution['value'] = require_once($resolution['path']);
        $resolution['status'] = 'loaded';
        $resolution['stage'] = 'load';
        $resolution['changed'] = true;
        $this->_loadedAddOns[$key] = $resolution;

        return $resolution;
    }

    private function addOnRuntimeKey(AddOn $addOn, string $primaryFile): string
    {
        $identifier = Val::isNotEmpty($addOn->addon_id)
            ? (string)$addOn->addon_id
            : (string)$addOn->name;

        return Str::lower(Str::trim($identifier)) . ':' . Path::normalize($primaryFile);
    }

    private function registrationResult(
        string $status,
        bool $executed = false,
        ?\Throwable $exception = null
    ): array {
        return Arr::make([
            'ok' => $exception === null && $status !== 'load_failed',
            'status' => $status,
            'executed' => $executed,
            'error' => $exception?->getMessage(),
            'exception' => $exception === null ? null : $exception::class,
        ])->toArray();
    }

    protected function callHook(AddOn $addOn, $hook)
    {
        $hook = Str::trim((string)$hook);
        $resolution = $this->resolvePrimaryFile($addOn);
        $result = Arr::make([
            'ok' => false,
            'hook' => $hook,
            'stage' => 'hook',
            'status' => 'pending',
            'optional' => false,
            'strategy' => null,
            'callable' => null,
            'attempted' => [],
            'primaryFile' => $resolution['path'],
            'primaryFileResolution' => $resolution,
            'error' => null,
            'exception' => null,
            'nextAction' => null,
        ]);

        if (Flag::isFalse($resolution['ok'])) {
            $result->set('status', $resolution['status']);
            $result->set('error', $resolution['error']);
            $result->set('nextAction', 'review_primary_file');

            return $result->toArray();
        }

        require_once($resolution['path']);

        $legacyHook = "{$addOn->name}_{$hook}";
        $namespace = Str::trim((string)$addOn->namespace, '\\');
        $candidates = Arr::make([]);
        if (Val::isNotEmpty($namespace)) {
            $candidates->push("{$namespace}\\{$legacyHook}");
        }
        $candidates->push($legacyHook)->unique()->values();
        $result->set('attempted', $candidates->toArray());

        foreach ($candidates as $candidate) {
            if (!Func::isCallable($candidate)) {
                continue;
            }

            try {
                Func::make($candidate)->call();
                $result->set('ok', true);
                $result->set('status', 'called');
                $result->set('strategy', $candidate === $legacyHook ? 'legacy' : 'namespaced');
                $result->set('callable', $candidate);
            } catch (\Throwable $exception) {
                $result->set('status', 'failed');
                $result->set('strategy', $candidate === $legacyHook ? 'legacy' : 'namespaced');
                $result->set('callable', $candidate);
                $result->set('error', $exception->getMessage());
                $result->set('exception', $exception::class);
                $result->set('nextAction', 'retry_hook');
            }

            return $result->toArray();
        }

		$result->set('ok', true);
		$result->set('status', 'skipped');
		$result->set('optional', true);

		return $result->toArray();
	}

    private function withHookFailure(array $result, array $hookResult, string $action): array
    {
        $result['ok'] = false;
        $result['changed'] = false;
        $result['stage'] = 'hook';
        $result['nextAction'] = Arr::getPath($hookResult, 'nextAction', "retry_{$action}");
        $result['error'] = Arr::getPath($hookResult, 'error', 'Add-on lifecycle hook failed.');
        $result['messages'][] = $result['error'];
        $result['modelStatus'] = $this->_model->status();

        return $result;
    }

    protected function resolvePrimaryFile(AddOn $addOn): array
    {
        $result = Arr::make([
            'ok' => false,
            'status' => 'pending',
            'strategy' => null,
            'path' => null,
            'configured' => null,
            'fallback' => null,
            'attempted' => [],
            'error' => null,
        ]);

        try {
            $name = $this->normalizeAddOnIdentifier($addOn->name, 'name');
            $primaryFile = Val::isEmpty($addOn->primary_file)
                ? 'main.php'
                : $this->normalizeDefinitionPath($addOn->primary_file, 'primary_file');
        } catch (\InvalidArgumentException $exception) {
            $result->set('status', 'unsafe_primary_file');
            $result->set('error', $exception->getMessage());

            return $result->toArray();
        }

        $addOnRoot = Path::normalize(resolve_path('addons' . DIRECTORY_SEPARATOR . $name));
        $configuredRoot = Path::normalize((string)$addOn->path);
        $configured = Val::isEmpty($configuredRoot)
            ? null
            : Path::normalize($configuredRoot . DIRECTORY_SEPARATOR . $primaryFile);
        $fallback = Path::normalize($addOnRoot . DIRECTORY_SEPARATOR . $primaryFile);
        $candidates = Arr::make([]);
        if (Val::isNotEmpty($configured)) {
            $candidates->push(['strategy' => 'configured', 'path' => $configured]);
        }
        if (Val::isEmpty($configured) || !Str::match($configured, $fallback)) {
            $candidates->push(['strategy' => 'fallback', 'path' => $fallback]);
        }

        $result->set('configured', $configured);
        $result->set('fallback', $fallback);
        $result->set('attempted', $candidates->map(fn ($candidate) => $candidate['path'])->toArray());

        foreach ($candidates as $candidate) {
            if (!(new File())->isReachable($candidate['path'])) {
                continue;
            }

            if (!$this->primaryFileIsContained($candidate['path'], $addOnRoot)) {
                $result->set('status', 'unsafe_primary_file');
                $result->set('error', 'Add-on primary file escapes its configured add-on root.');

                return $result->toArray();
            }

            $result->set('ok', true);
            $result->set('status', 'resolved');
            $result->set('strategy', $candidate['strategy']);
            $result->set('path', Path::normalize(realpath($candidate['path'])));

            return $result->toArray();
        }

        $result->set('status', 'missing_primary_file');
        $result->set('error', 'Add-on primary file is not reachable from configured or canonical paths.');

        return $result->toArray();
    }

    private function primaryFileIsContained(string $primaryFile, string $addOnRoot): bool
    {
        $resolvedFile = realpath($primaryFile);
        $resolvedRoot = realpath($addOnRoot);
        if (Val::isEmpty($resolvedFile) || Val::isEmpty($resolvedRoot)) {
            return false;
        }

        $resolvedFile = Path::normalize($resolvedFile);
        $resolvedRoot = Path::normalize($resolvedRoot);
        if (Str::match(DIRECTORY_SEPARATOR, '\\')) {
            $resolvedFile = Str::lower($resolvedFile);
            $resolvedRoot = Str::lower($resolvedRoot);
        }

        $rootPrefix = Str::endsWith($resolvedRoot, DIRECTORY_SEPARATOR)
            ? $resolvedRoot
            : $resolvedRoot . DIRECTORY_SEPARATOR;

        return Str::startsWith($resolvedFile, $rootPrefix);
    }

    public function uploadAddonFile($file, $destination)
    {
        $fileSystem = new FileSystem();
        $fileSystem->upload($file, $destination);
    }

    public function moveAddonFile($source, $destination)
    {
        $fileSystem = new FileSystem();
        $fileSystem->move($source, $destination);
    }

    public function copyAddonFile($source, $destination)
    {
        $fileSystem = new FileSystem();
        $fileSystem->copy($source, $destination);
    }

    public function deleteAddonFile($path)
    {
        $fileSystem = new FileSystem();
        $fileSystem->delete($path);
    }

    public function deleteAddonDirectory($path)
    {
        $fileSystem = new FileSystem();
        $fileSystem->deleteDirectory($path);
    }

    protected function getAddOnById($addOnId)
    {
        $data = $this->_model->read(['addon_id'=>$addOnId]);
        $addon = new AddOn;
        $addon->assign($data->data());
        return $addon;
    }

    protected function getAddOnData($name)
    {
        $name = $this->normalizeAddOnIdentifier($name, 'name');
        $definitionPath = resolve_path('addons' . DIRECTORY_SEPARATOR . $name  . DIRECTORY_SEPARATOR . 'definition.json');
        if (!(new File())->isReachable($definitionPath)) {
            throw new \InvalidArgumentException("Add-on definition not found for {$name}.");
        }

        $contents = File::readContents($definitionPath);
        $decodedObject = HTTP::jsonDecode($contents, false);
        if (!is_object($decodedObject)) {
            throw $this->invalidDefinition('definition', 'a valid JSON object');
        }

        $definition = Arr::make(HTTP::jsonDecode($contents, true, []));
        $rawName = $definition->get('name');
        if (Val::isNotNull($rawName) && !Str::is($rawName)) {
            throw $this->invalidDefinition('name', 'a safe add-on identifier');
        }
        $definedName = Val::isEmpty($rawName)
            ? $name
            : $this->normalizeAddOnIdentifier($rawName, 'name');

        if ($definedName !== $name) {
            throw $this->invalidDefinition('name', 'the add-on directory identifier');
        }

        $rawVersion = $definition->get('version');
        if (Val::isNotNull($rawVersion) && !Str::is($rawVersion)) {
            throw $this->invalidDefinition('version', 'a semantic version string');
        }
        $version = Val::isEmpty($rawVersion)
            ? '0.0.0'
            : Str::trim($rawVersion);
        if (!Str::matchPattern($version, '/^[0-9]+(?:\.[0-9]+){0,3}(?:[-+][0-9A-Za-z.-]+)?$/')) {
            throw $this->invalidDefinition('version', 'a semantic version string');
        }

        $rawNamespace = $definition->get('namespace');
        if (Val::isNotNull($rawNamespace) && !Str::is($rawNamespace)) {
            throw $this->invalidDefinition('namespace', 'a valid PHP namespace');
        }
        $namespace = Val::isEmpty($rawNamespace)
            ? ''
            : Str::trim($rawNamespace, '\\');
        if (Val::isNotEmpty($namespace) && !Str::matchPattern(
            $namespace,
            '/^(?:[A-Za-z_][A-Za-z0-9_]*)(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/'
        )) {
            throw $this->invalidDefinition('namespace', 'a valid PHP namespace');
        }

        $rawPrimaryFile = $definition->get('primary_file');
        if (Val::isNotNull($rawPrimaryFile) && !Str::is($rawPrimaryFile)) {
            throw $this->invalidDefinition('primary_file', 'a relative path within the add-on root');
        }
        $primaryFile = Val::isEmpty($rawPrimaryFile)
            ? 'main.php'
            : $this->normalizeDefinitionPath($rawPrimaryFile, 'primary_file');

        $libraries = $definition->get('libraries');
        if (Val::isNull($libraries)) {
            $libraries = [];
        }
        if (!Arr::is($libraries)) {
            throw $this->invalidDefinition('libraries', 'an array of Composer package names');
        }

        $libraries = Arr::make($libraries)
            ->map(function ($library) {
                if (!Str::is($library)) {
                    throw $this->invalidDefinition('libraries', 'an array of Composer package names');
                }

                $normalized = $this->sanitizeLibraryName($library);
                if (Val::isNull($normalized)) {
                    throw $this->invalidDefinition('libraries', 'an array of Composer package names');
                }

                return $normalized;
            })
            ->unique()
            ->values()
            ->toArray();

        return (object)Arr::make([
            'name' => $definedName,
            'version' => $version,
            'namespace' => $namespace,
            'primary_file' => $primaryFile,
            'libraries' => $libraries,
            'description' => (string)($definition->get('description') ?? ''),
        ])->toArray();
    }

    private function normalizeAddOnIdentifier(mixed $identifier, string $field): string
    {
        if (!Str::is($identifier)) {
            throw $this->invalidDefinition($field, 'a safe add-on identifier');
        }

        $identifier = Str::trim($identifier);
        if (!Str::matchPattern($identifier, '/^[A-Za-z0-9][A-Za-z0-9._-]*$/')) {
            throw $this->invalidDefinition($field, 'a safe add-on identifier');
        }

        return $identifier;
    }

    private function normalizeDefinitionPath(mixed $path, string $field): string
    {
        if (!Str::is($path)) {
            throw $this->invalidDefinition($field, 'a relative path within the add-on root');
        }

        $path = Path::normalize(Str::trim($path));
        $segments = Str::make($path)->split(DIRECTORY_SEPARATOR)->val();
        $isAbsolute = Str::startsWith($path, DIRECTORY_SEPARATOR)
            || Str::matchPattern($path, '/^[A-Za-z]:/')
            || Arr::has($segments, '..', true);

        if (Val::isEmpty($path) || $isAbsolute) {
            throw $this->invalidDefinition($field, 'a relative path within the add-on root');
        }

        return Str::replace($path, '\\', '/');
    }

    private function invalidDefinition(string $field, string $expected): \InvalidArgumentException
    {
        return new \InvalidArgumentException("Invalid add-on definition field '{$field}': expected {$expected}.");
    }

    protected function addOnPath(string $name): string
    {
        $root = realpath(resolve_path('addons'));
        $path = realpath(resolve_path('addons' . DIRECTORY_SEPARATOR . $name));

        if (Val::isEmpty($root) || Val::isEmpty($path)) {
            throw new \InvalidArgumentException("Add-on path not found for {$name}.");
        }

        $root = Path::normalize($root);
        $path = Path::normalize($path);
        $rootPrefix = Str::endsWith($root, DIRECTORY_SEPARATOR)
            ? $root
            : $root . DIRECTORY_SEPARATOR;

        if ($path !== $root && !Str::startsWith($path, $rootPrefix)) {
            throw new \InvalidArgumentException("Add-on path escapes the configured root for {$name}.");
        }

        return Str::replace($path, '\\', '/');
    }

    protected function dependencyCommands(array $libraries, string $operation): array
    {
        if (!Arr::has(['require', 'remove'], $operation, true)) {
            throw new \InvalidArgumentException('Unsupported dependency operation.');
        }

        return Arr::make($libraries)
            ->map(fn ($library) => $this->sanitizeLibraryName((string)$library))
            ->filter(fn ($library) => Val::isNotEmpty($library))
            ->map(fn ($library) => "composer {$operation} {$library}")
            ->values()
            ->toArray();
    }

    protected function sanitizeLibraryName(string $library): ?string
    {
        $library = Str::lower(Str::trim($library));
        if (!Str::matchPattern($library, '/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/')) {
            return null;
        }

        return $library;
    }

    protected function isInstalled(string $name): bool
    {
        $this->_model->clear();
        $this->_model->read(['name' => $name]);

        return Val::isNotEmpty($this->_model->id());
    }

    protected function installedAddOns(): mixed
    {
        $this->_model->clear();
        $this->_model->read();
        $records = $this->_model->all();

        if ($records instanceof ICollection) {
            $records = $records->toArray(true);
        } elseif ($records instanceof \Traversable) {
            $records = iterator_to_array($records);
        } else {
            $records = Arr::toArray($records, true);
        }

        return Arr::make($records)
            ->map(fn ($addOn) => $this->normalizeInstalledAddOn($addOn))
            ->values()
            ->toArray();
    }

    private function normalizeInstalledAddOn(mixed $addOn): array
    {
        if (Arr::is($addOn)) {
            return $addOn;
        }

        if ($addOn instanceof ICollection) {
            return $addOn->toArray(true);
        }

        if (Func::isCallable([$addOn, 'data'])) {
            return Arr::toArray(Func::make([$addOn, 'data'])->call(), true);
        }

        if (is_object($addOn)) {
            return get_object_vars($addOn);
        }

        return [];
    }

    protected function datasourceManager(): mixed
    {
        return instance('datasource');
    }

    protected function captureOutput(callable $operation): string
    {
        ob_start();

        try {
            $operation();

            return (string)ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }
    }

    protected function runDependencyCommands(array $commands): string
    {
        $system = new System();
        $system->cwd(APP_ROOT);
        $status = '';

        foreach ($commands as $command) {
            $system->run($command);
            $status .= $system->response();
        }

        return $status;
    }

    private function lifecycleResult(string $action, string $addOn = '', mixed $modelStatus = null, string $query = ''): array
    {
        $hasModelStatus = Val::isNotNull($modelStatus);
        $ok = !$hasModelStatus || $modelStatus === Storage::STATUS_SUCCESS;

        return Arr::make([
            'ok' => $ok,
            'action' => $action,
            'addon' => $addOn,
            'changed' => $hasModelStatus && $ok,
            'stage' => $hasModelStatus ? ($ok ? 'complete' : 'registration') : 'pending',
            'nextAction' => $ok ? null : "retry_{$action}",
            'error' => $ok ? null : 'Add-on registration could not be updated.',
            'messages' => $ok ? [] : ['Add-on registration could not be updated.'],
            'dependencies' => [],
            'hooks' => [],
			'migrations' => [],
			'population' => [],
            'modelStatus' => $modelStatus,
            'query' => $query,
        ])->toArray();
    }

    private function failedLifecycleResult(string $action, string $addOn, \Throwable $exception): array
    {
        $result = $this->lifecycleResult($action, $addOn);
        $result['ok'] = false;
        $result['stage'] = 'failed';
        $result['nextAction'] = "retry_{$action}";
        $result['error'] = $exception->getMessage();
        $result['messages'][] = $exception->getMessage();

        return $result;
    }

    private function lifecycleBatchResult(string $action, array $results): array
    {
        $failures = Arr::make($results)
            ->filter(fn ($result) => Flag::isFalse(Arr::getPath($result, 'ok', false)))
            ->values()
            ->toArray();
        $changes = Arr::make($results)
            ->filter(fn ($result) => Flag::parseBool(Arr::getPath($result, 'changed', false)))
            ->values()
            ->toArray();
        $total = Arr::size($results);
        $failed = Arr::size($failures);

        return Arr::make([
            'ok' => $failed === 0,
            'action' => $action,
            'changed' => Arr::isNotEmpty($changes),
            'total' => $total,
            'succeeded' => $total - $failed,
            'failed' => $failed,
            'results' => $results,
        ])->toArray();
    }

    protected function autoload()
    {
        spl_autoload_register(function ($class) {
            // TODO: clean this up and add validation
            $file = str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
            $path = explode(DIRECTORY_SEPARATOR, $file);
            if ( count($path) > 2 ) {
                $path[0] = strtolower($path[0]);
                $path[1] = strtolower($path[1]);

                array_splice( $path, 2, 0, 'logic' ); // splice in at position 2

                $file = implode(DIRECTORY_SEPARATOR, $path);
                $file = resolve_path($file);
            }
            if (file_exists($file)) {
                require_once($file);
                return true;
            }
            return false;
        });
    }
}
