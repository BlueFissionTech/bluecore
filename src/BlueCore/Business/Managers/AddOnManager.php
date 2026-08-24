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

        $datasource->populate();

        $hookResult = $this->callHook($addon, 'install');
        if (Arr::is($hookResult)) {
            $result['hooks'][] = $hookResult;
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
        $this->_model->write(['addon_id' => $addOnId, 'is_active' => 1]);

        return $this->lifecycleResult('activate', (string)$addOnId, $this->_model->status(), $this->_model->query());
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
        $this->_model->write(['addon_id' => $addOnId, 'is_active' => 0]);

        return $this->lifecycleResult('deactivate', (string)$addOnId, $this->_model->status(), $this->_model->query());
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
            'status' => 'pending',
            'strategy' => null,
            'callable' => null,
            'attempted' => [],
            'primaryFile' => $resolution['path'],
            'primaryFileResolution' => $resolution,
            'error' => null,
        ]);

        if (Flag::isFalse($resolution['ok'])) {
            $result->set('status', $resolution['status']);
            $result->set('error', $resolution['error']);

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

            Func::make($candidate)->call();
            $result->set('ok', true);
            $result->set('status', 'called');
            $result->set('strategy', $candidate === $legacyHook ? 'legacy' : 'namespaced');
            $result->set('callable', $candidate);

            return $result->toArray();
        }

        $result->set('status', 'missing_callable');
        $result->set('error', 'No compatible lifecycle hook callable was found.');

        return $result->toArray();
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
