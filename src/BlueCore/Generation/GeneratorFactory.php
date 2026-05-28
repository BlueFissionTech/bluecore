<?php
namespace BlueFission\BlueCore\Generation;

class GeneratorFactory
{
    private $aiCodeGenerator;
    private $aiCopyGenerator;

    public function __construct( IAICodeGenerator $aiCodeGenerator, IAICopyGenerator $aiCopyGenerator )
    {
        $this->aiCodeGenerator = $aiCodeGenerator;
        $this->aiCopyGenerator = $aiCopyGenerator;
    }

    public function create(string $name, array|object $config, string $outputPath = null): ?IGenerator
    {
        $aiCodeGenerator = $this->aiCodeGenerator;
        $aiCopyGenerator = $this->aiCopyGenerator;
        $type = strtolower($name);
        $templatePath = $this->configValue($config, 'templatePath', $this->configValue($config, 'template_path', ''));
        $resolvedOutputPath = $outputPath ?? $this->configValue($config, 'outputPath', $this->configValue($config, 'output_path', ''));

        switch ($type) {
            case 'controller':
                return new ControllerGenerator($templatePath, $resolvedOutputPath, $aiCodeGenerator);
            case 'module':
                return new AdminModuleGenerator($this->configValue($config, 'tableName', $this->configValue($config, 'table_name', '')));
            case 'query':
                return new QueryGenerator($templatePath, $resolvedOutputPath, $aiCodeGenerator);
            case 'repository':
                return new RepositoryGenerator($templatePath, $resolvedOutputPath, $aiCodeGenerator);
            case 'scaffold':
                return new ScaffoldGenerator($templatePath, $resolvedOutputPath, $aiCodeGenerator);
            case 'valueobject':
                return new ValueObjectGenerator($templatePath, $resolvedOutputPath, $aiCodeGenerator);
            case 'content':
                return new ContentGenerator($aiCopyGenerator);
            case 'admin_module':
                return new AdminModuleGenerator($this->configValue($config, 'tableName', $this->configValue($config, 'table_name', '')));
            case 'css':
                return new CSSGenerator([]);
            case 'template':
                return new HTMLGenerator([]);
            default:
                return null;
        }
    }

    private function configValue(array|object $config, string $key, mixed $default = null): mixed
    {
        if (is_array($config)) {
            return $config[$key] ?? $default;
        }

        return $config->{$key} ?? $default;
    }
}
