<?php

namespace BlueFission\Tests\BlueCore;

use BlueFission\BlueCore\Security;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    public function testMissingKeyRaisesAnIntentionalException(): void
    {
        $key = getenv('APP_KEY');
        $property = new \ReflectionProperty(Security::class, '_appKey');
        $original = $property->getValue();
        try {
            putenv('APP_KEY');
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Application key not set.');
            Security::init();
        } finally {
            $key === false ? putenv('APP_KEY') : putenv('APP_KEY=' . $key);
            $property->setValue(null, $original);
        }
    }
}
