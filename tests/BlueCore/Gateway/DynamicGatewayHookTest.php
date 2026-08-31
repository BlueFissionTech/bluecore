<?php

namespace BlueFission\Tests\BlueCore\Gateway;

use BlueFission\Arr;
use BlueFission\BlueCore\Gateway\DynamicGateway;
use BlueFission\DevElation as Dev;
use BlueFission\Services\Request;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class DynamicGatewayHookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDevElation();
    }

    protected function tearDown(): void
    {
        $this->resetDevElation();

        parent::tearDown();
    }

    public function testArgumentsRemainUnchangedWhileDevElationIsInactive(): void
    {
        Dev::filter(DynamicGateway::FILTER_ARGUMENTS, fn(array $arguments): array => [
            ...$arguments,
            'filtered' => true,
        ]);
        $arguments = ['request_id' => 'request-1'];

        (new DynamicGateway())->process(new Request(), $arguments);

        $this->assertSame(['request_id' => 'request-1'], $arguments);
    }

    public function testLegacyAndNeutralFiltersRunInDeterministicOrder(): void
    {
        Dev::up();
        Dev::filter(
            DynamicGateway::LEGACY_FILTER_ARGUMENTS,
            fn(array $arguments): Arr => Arr::make($arguments)->push('legacy')
        );
        Dev::filter(
            DynamicGateway::FILTER_ARGUMENTS,
            fn(array|Arr $arguments): array => [
                ...Arr::toArray($arguments, true),
                'neutral-late',
            ],
            20
        );
        Dev::filter(
            DynamicGateway::FILTER_ARGUMENTS,
            fn(array $arguments): Arr => Arr::make($arguments)->push('neutral-early'),
            5
        );
        $arguments = [];

        (new DynamicGateway())->process(new Request(), $arguments);

        $this->assertSame(['legacy', 'neutral-early', 'neutral-late'], $arguments);
    }

    public function testFilterMustReturnArrayCompatibleArguments(): void
    {
        Dev::up();
        Dev::filter(DynamicGateway::FILTER_ARGUMENTS, fn(): string => 'invalid');
        $arguments = [];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(DynamicGateway::FILTER_ARGUMENTS);

        (new DynamicGateway())->process(new Request(), $arguments);
    }

    private function resetDevElation(): void
    {
        Dev::down();
        $reflection = new \ReflectionClass(Dev::class);

        foreach (['_filters', '_actions', '_listeners', '_config'] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setValue(null, []);
        }
    }
}
