<?php

namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Arr;
use BlueFission\Connections\Database\MySQLLink;
use BlueFission\Data\FileSystem;
use BlueFission\Data\Storage\Storage;
use BlueFission\Net\HTTP;
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
            $results[] = $this->install($addOn->name, $installDependencies);
        }
        return $results;
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
            if (!$addOn->is_active) {
                $results[] = $this->activate($addOn->addon_id);
            }
        }
        return $results;
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

        return $this->_model->all();
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
        return Arr::make([
            'ok' => true,
            'action' => $action,
            'addon' => $addOn,
            'changed' => false,
            'stage' => 'pending',
            'nextAction' => null,
            'error' => null,
            'messages' => [],
            'dependencies' => [],
            'modelStatus' => $modelStatus,
            'query' => $query,
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
