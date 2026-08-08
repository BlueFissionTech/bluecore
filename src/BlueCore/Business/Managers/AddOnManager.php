<?php

namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Arr;
use BlueFission\Connections\Database\MySQLLink;
use BlueFission\Data\FileSystem;
use BlueFission\Data\Storage\Storage;
use BlueFission\Flag;
use BlueFission\Services\Service;
use BlueFission\Utils\Loader;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use BlueFission\BlueCore\Domain\AddOn\Models\AddOnModel;
use BlueFission\BlueCore\Domain\AddOn\AddOn;
use BlueFission\Str;
use BlueFission\System\System;
use BlueFission\Val;

class AddOnManager extends Service
{
    private $_loader;
    protected $_model;

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
        $addOnPath = $this->addOnPath((string)$name);
        $data = $this->getAddOnData($name);
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
        ob_start();
        $datasource->runMigrations($name);
        $datasource->populate();
        $result['messages'][] = ob_get_contents();
        ob_end_clean();

        $this->callHook($addon, 'install');
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
            $this->callHook($addon, 'uninstall');

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
        $addOns = $this->installedAddOns();
        $results = [];
        foreach ($addOns as $addOn) {
            $record = Arr::make($addOn);
            if (Flag::parseBool($record->get('is_active'))) {
                continue;
            }

            $addOnId = $record->get('addon_id');
            if (Val::isEmpty($addOnId)) {
                $results[] = $this->failedLifecycleResult(
                    'activate',
                    '',
                    new \UnexpectedValueException('Persisted add-on row is missing addon_id.')
                );
                continue;
            }

            $results[] = $this->activate($addOnId);
        }

        return $this->lifecycleBatchResult('activate_all', $results);
    }

    public function deactivate($addOnId): array
    {
        $this->_model->write(['addon_id' => $addOnId, 'is_active' => 0]);

        return $this->lifecycleResult('deactivate', (string)$addOnId, $this->_model->status(), $this->_model->query());
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

    public function loadActivatedAddOns()
    {
        $addOns = $this->_model->getActivatedAddOns();
        
        foreach ($addOns as $addOn) {
            $object = new AddOn;
            $object->assign($addOn);
            // $object->path = resolve_path('addons' . DIRECTORY_SEPARATOR . $addOn);
            // $object->primary_file = 'main.php';
            $addOn = $object;
            $this->loadAddOn($addOn);
        }
    }

    protected function loadAddOn(AddOn $addOn)
    {
        $primaryFile = $addOn->path . DIRECTORY_SEPARATOR . $addOn->primary_file;
        // $primaryFile = resolve_path('addons' . DIRECTORY_SEPARATOR . $addon . DIRECTORY_SEPARATOR . 'main.php');
        if ($primaryFile != DIRECTORY_SEPARATOR && (new File())->exists($primaryFile)) {
            require_once($primaryFile);
        }
    }

    protected function callHook(AddOn $addOn, $hook)
    {
        $primaryFile = $addOn->path . DIRECTORY_SEPARATOR . $addOn->primary_file;
        if ((new File())->exists($primaryFile)) {
            require_once($primaryFile);

            $hookFunction = "{$addOn->name}_{$hook}";
            if (function_exists($hookFunction)) {
                $hookFunction();
            }
        }
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
        $definitionPath = resolve_path('addons' . DIRECTORY_SEPARATOR . $name  . DIRECTORY_SEPARATOR . 'definition.json');
        if (!(new File())->isReachable($definitionPath)) {
            throw new \InvalidArgumentException("Add-on definition not found for {$name}.");
        }

        $data = json_decode(File::readContents($definitionPath));
        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Add-on definition is invalid JSON for {$name}.");
        }

        if (Val::isEmpty($data->name ?? null)) {
            $data->name = $name;
        }

        $data->libraries = Arr::toArray($data->libraries ?? [], true);
        $data->primary_file = $data->primary_file ?? 'main.php';

        return $data;
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

        return Arr::make(Arr::toArray($this->_model->all(), true))
            ->map(fn ($addOn) => Arr::toArray($addOn, true))
            ->values()
            ->toArray();
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
