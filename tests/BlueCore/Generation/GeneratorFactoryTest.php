<?php

namespace BlueFission\Tests\BlueCore\Generation;

use BlueFission\BlueCore\Generation\ContentGenerator;
use BlueFission\BlueCore\Generation\ControllerGenerator;
use BlueFission\BlueCore\Generation\CSSGenerator;
use BlueFission\BlueCore\Generation\GeneratorFactory;
use BlueFission\BlueCore\Generation\Gpt4CodeGenerator;
use BlueFission\BlueCore\Generation\HTMLGenerator;
use BlueFission\BlueCore\Generation\IAICodeGenerator;
use BlueFission\BlueCore\Generation\IAICopyGenerator;
use BlueFission\BlueCore\Generation\QueryGenerator;
use BlueFission\BlueCore\Generation\RepositoryGenerator;
use BlueFission\BlueCore\Generation\ScaffoldGenerator;
use BlueFission\BlueCore\Generation\ValueObjectGenerator;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

class GeneratorFactoryTest extends TestCase
{
    public function testFactoryCreatesKnownGeneratorTypes(): void
    {
        $factory = new GeneratorFactory(new FakeCodeGenerator(), new FakeCopyGenerator());
        $config = ['templatePath' => __FILE__, 'outputPath' => sys_get_temp_dir()];

        $this->assertInstanceOf(ControllerGenerator::class, $factory->create('controller', $config));
        $this->assertInstanceOf(QueryGenerator::class, $factory->create('query', $config));
        $this->assertInstanceOf(RepositoryGenerator::class, $factory->create('repository', $config));
        $this->assertInstanceOf(ScaffoldGenerator::class, $factory->create('scaffold', $config));
        $this->assertInstanceOf(ValueObjectGenerator::class, $factory->create('valueobject', $config));
        $this->assertInstanceOf(ContentGenerator::class, $factory->create('content', $config));
        $this->assertInstanceOf(CSSGenerator::class, $factory->create('css', $config));
        $this->assertInstanceOf(HTMLGenerator::class, $factory->create('template', $config));
    }

    public function testFactoryReturnsNullForUnknownGenerator(): void
    {
        $factory = new GeneratorFactory(new FakeCodeGenerator(), new FakeCopyGenerator());

        $this->assertNull($factory->create('missing', []));
    }

    public function testFactorySupportsAliasesAndFailFastCreation(): void
    {
        $factory = new GeneratorFactory(new FakeCodeGenerator(), new FakeCopyGenerator());
        $config = ['templatePath' => __FILE__, 'outputPath' => sys_get_temp_dir(), 'tableName' => 'users'];

        $this->assertContains('controller', $factory->supportedTypes());
        $this->assertInstanceOf(HTMLGenerator::class, $factory->create('html', $config));
        $this->assertInstanceOf(ValueObjectGenerator::class, $factory->create('value object', $config));
        $this->assertInstanceOf(\BlueFission\BlueCore\Generation\AdminModuleGenerator::class, $factory->createOrFail('admin-module', $config));

        $this->expectException(\InvalidArgumentException::class);
        $factory->createOrFail('missing', $config);
    }

    public function testBaseGeneratorWritesGeneratedCodeToNormalizedOutputPath(): void
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-generator-' . uniqid('', true);
        $template = File::ensureFile($tmpDir . DIRECTORY_SEPARATOR . 'template.php', '<?php // template', true);

        try {
            $generator = new ControllerGenerator($template, $tmpDir . '/output', new FakeCodeGenerator());

            $this->assertTrue($generator->generate('GeneratedController', 'create a controller generated'));
            $this->assertSame('<?php', File::readContents($tmpDir . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR . 'GeneratedController.php'));
        } finally {
            $this->removeDir($tmpDir);
        }
    }

    public function testGpt4CodeGeneratorUsesInjectedClient(): void
    {
        $generator = new Gpt4CodeGenerator('token', new FakeCompletionClient(' class Demo {} '));

        $this->assertSame('class Demo {}', $generator->generateCode('<?php', 'create demo'));
    }

    public function testGpt4CodeGeneratorRejectsInvalidInjectedClient(): void
    {
        $generator = new Gpt4CodeGenerator('token', new \stdClass());

        $this->expectException(\RuntimeException::class);
        $generator->generateCode('<?php', 'create demo');
    }

    private function removeDir($dir): void
    {
        if (!$dir || !is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}

class FakeCodeGenerator implements IAICodeGenerator
{
    public function generateCode(string $template, string $userPrompt): ?string
    {
        return '<?php';
    }

    public function generateClassName(string $userPrompt): ?string
    {
        return 'GeneratedClass';
    }
}

class FakeCopyGenerator implements IAICopyGenerator
{
    public function generateText(string $prompt): ?string
    {
        return 'copy';
    }

    public function generateImage(string $prompt): ?string
    {
        return 'image';
    }
}

class FakeCompletionClient
{
    public function __construct(private string $text)
    {
    }

    public function post(string $url, array $options): FakeCompletionResponse
    {
        return new FakeCompletionResponse($this->text);
    }
}

class FakeCompletionResponse
{
    public function __construct(private string $text)
    {
    }

    public function getBody(): string
    {
        return json_encode([
            'choices' => [
                ['text' => $this->text],
            ],
        ]);
    }
}
