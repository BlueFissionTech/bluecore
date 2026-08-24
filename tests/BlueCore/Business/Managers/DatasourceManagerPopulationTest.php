<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\Arr;
use BlueFission\BlueCore\Business\Managers\DatasourceManager;
use PHPUnit\Framework\TestCase;

class DatasourceManagerPopulationTest extends TestCase
{
    public function testMissingGeneratorDirectoryReturnsStructuredNoOp(): void
    {
        $result = (new ExecutablePopulationManager([], directoryExists: false))->populate();

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['changed']);
        $this->assertSame('complete', $result['stage']);
        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['results']);
    }

    public function testRootSeederOrderProducesStructuredSuccess(): void
    {
        $manager = new ExecutablePopulationManager([
            'RootSeeder.php',
            'FirstSeeder.php',
            'SecondSeeder.php',
        ], rootOrder: ['SecondSeeder', 'FirstSeeder.php']);

        $result = $manager->populate(true);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['changed']);
        $this->assertSame('complete', $result['stage']);
        $this->assertSame(2, $result['populated']);
        $this->assertSame(['SecondSeeder.php', 'FirstSeeder.php'], $manager->populated());
        $this->assertSame([true, true], $manager->autoValues());
    }

    public function testGeneratorFailureStopsLaterPopulationAndPreservesDiagnostics(): void
    {
        $manager = new ExecutablePopulationManager([
            'FirstSeeder.php',
            'FailingSeeder.php',
            'AfterSeeder.php',
        ]);
        $manager->failOn('FailingSeeder.php');

        $result = $manager->populate();

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['changed']);
        $this->assertSame('population', $result['stage']);
        $this->assertSame(1, $result['populated']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('review_population_failure', $result['nextAction']);
        $this->assertSame('FailingSeeder.php failed.', $result['error']);
        $this->assertSame(['FirstSeeder.php', 'FailingSeeder.php'], $manager->populated());
        $this->assertSame('failed', $result['results'][1]['status']);
        $this->assertSame(\RuntimeException::class, $result['results'][1]['exception']);
    }
}

class ExecutablePopulationManager extends DatasourceManager
{
    private array $completed = [];
    private array $auto = [];
    private ?string $failedGenerator = null;

    public function __construct(
        private array $available,
        private bool $directoryExists = true,
        private array $rootOrder = []
    ) {
        $this->_generatorDir = 'virtual' . DIRECTORY_SEPARATOR;
    }

    public function failOn(?string $generator): void
    {
        $this->failedGenerator = $generator;
    }

    public function populated(): array
    {
        return $this->completed;
    }

    public function autoValues(): array
    {
        return $this->auto;
    }

    protected function generatorDirectoryExists(): bool
    {
        return $this->directoryExists;
    }

    protected function loadGenerators(): array
    {
        return Arr::make(['.', '..'])->merge($this->available)->toArray();
    }

    protected function findClassName($file): ?string
    {
        return basename((string)$file);
    }

    protected function includeGenerator(string $path): void
    {
    }

    protected function generatorInstance(string $classname): object
    {
        if ($classname === 'RootSeeder.php' && Arr::isNotEmpty($this->rootOrder)) {
            return new TestRootSeeder($this->rootOrder);
        }

        return new TestPopulationGenerator(
            $classname,
            $this->failedGenerator === $classname,
            function (string $name, bool $auto): void {
                $this->completed[] = $name;
                $this->auto[] = $auto;
            }
        );
    }
}

class TestRootSeeder
{
    public function __construct(private array $order)
    {
    }

    public function seeders(): array
    {
        return $this->order;
    }
}

class TestPopulationGenerator
{
    public function __construct(
        private string $name,
        private bool $fails,
        private $onPopulate
    ) {
    }

    public function populate(bool $auto): void
    {
        ($this->onPopulate)($this->name, $auto);

        if ($this->fails) {
            throw new \RuntimeException("{$this->name} failed.");
        }
    }
}
