<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use BlueFission\BlueCore\Generation\GeneratorFactory;
use BlueFission\BlueCore\Generation\IAICodeGenerator;
use BlueFission\BlueCore\Generation\IAICopyGenerator;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;

final class ExampleCodeGenerator implements IAICodeGenerator
{
    public function generateCode(string $template, string $userPrompt): ?string
    {
        $className = $this->generateClassName($userPrompt) ?? 'GeneratedExample';

        return str_replace('ExampleClass', $className, $template);
    }

    public function generateClassName(string $userPrompt): ?string
    {
        return 'GeneratedReportController';
    }
}

final class ExampleCopyGenerator implements IAICopyGenerator
{
    public function generateText(string $prompt): ?string
    {
        return 'Generated copy for: ' . $prompt;
    }

    public function generateImage(string $prompt): ?string
    {
        return 'image-placeholder:' . sha1($prompt);
    }
}

$runtime = bluecore_example_runtime_path('generator-factory');
$outputPath = Path::ensureDir($runtime . DIRECTORY_SEPARATOR . 'generated');
$templatePath = File::writeAtomic(
    $runtime . DIRECTORY_SEPARATOR . 'controller-template.php',
    "<?php\n\nfinal class ExampleClass\n{\n}\n"
);

$factory = new GeneratorFactory(new ExampleCodeGenerator(), new ExampleCopyGenerator());
$config = (object)[
    'templatePath' => $templatePath,
    'outputPath' => $outputPath,
];

$created = [];
foreach (['controller', 'query', 'repository', 'scaffold', 'valueobject', 'content', 'css', 'template', 'missing'] as $name) {
    $generator = $factory->create($name, $config);
    $created[$name] = $generator ? [
        'class' => get_class($generator),
        'type' => $generator->getType(),
    ] : null;
}

$controller = $factory->create('controller', $config);
$controllerGenerated = $controller?->generate('ReportController', 'Create a controller for reporting');
$generatedFile = $outputPath . DIRECTORY_SEPARATOR . 'ReportController.php';

$copy = $factory->create('content', $config)?->generate('copy', 'Report on monthly account activity');

bluecore_example_json([
    'runtime' => Path::normalize($runtime),
    'generators' => $created,
    'controller_generated' => $controllerGenerated === true && file_exists($generatedFile),
    'controller_file' => Path::normalize($generatedFile),
    'copy_preview' => $copy,
]);
