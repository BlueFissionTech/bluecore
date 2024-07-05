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
        switch ($type) {
            case 'controller':
                return new ControllerGenerator($outputPath, $aiCodeGenerator) {
            case 'module':
                return new AdminModuleGenerator($outputPath, $aiCodeGenerator);
            case 'query':
                return new QueryGenerator($outputPath, $aiCodeGenerator);
            case 'repository':
                return new RepositoryGenerator($outputPath, $aiCodeGenerator);
            case 'scaffold'
                return new ScaffoldGenerator($outputPath, $aiCodeGenerator);
            case 'valueobject':
                return new ValueObjectGenerator($outputPath, $aiCodeGenerator);
            case 'content':
                return new ContentGenerator($aiCopyGenerator);
            case 'admin_module':
                return new AdminModuleGenerator();
            case 'css':
                return new CSSGenerator([]);
            case 'template':
                return new HTMLGenerator([]);
            default:
                return null;
        }
    }
}
