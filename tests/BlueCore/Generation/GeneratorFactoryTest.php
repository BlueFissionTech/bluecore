<?php

namespace BlueFission\Tests\BlueCore\Generation;

use BlueFission\BlueCore\Generation\ContentGenerator;
use BlueFission\BlueCore\Generation\ControllerGenerator;
use BlueFission\BlueCore\Generation\CSSGenerator;
use BlueFission\BlueCore\Generation\GeneratorFactory;
use BlueFission\BlueCore\Generation\HTMLGenerator;
use BlueFission\BlueCore\Generation\IAICodeGenerator;
use BlueFission\BlueCore\Generation\IAICopyGenerator;
use BlueFission\BlueCore\Generation\QueryGenerator;
use BlueFission\BlueCore\Generation\RepositoryGenerator;
use BlueFission\BlueCore\Generation\ScaffoldGenerator;
use BlueFission\BlueCore\Generation\ValueObjectGenerator;
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
