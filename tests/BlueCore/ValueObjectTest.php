<?php

namespace BlueFission\Tests\BlueCore;

use BlueFission\BlueCore\ValueObject;
use PHPUnit\Framework\TestCase;

class ValueObjectTest extends TestCase
{
    public function testAssignKeepsDefaultsForMissingFields(): void
    {
        $value = new TestValueObject(['name' => 'alpha']);

        $this->assertSame('alpha', $value->name);
        $this->assertSame('draft', $value->status);
    }

    public function testAssignAcceptsObjects(): void
    {
        $payload = (object)[
            'name' => 'beta',
            'status' => 'active',
        ];

        $value = new TestValueObject($payload);

        $this->assertSame('beta', $value->name);
        $this->assertSame('active', $value->status);
    }
}

class TestValueObject extends ValueObject
{
    public string $name = '';
    public string $status = 'draft';
}
