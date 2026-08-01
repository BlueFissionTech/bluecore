<?php

namespace BlueFission\Tests\BlueCore\Gateway;

use BlueFission\BlueCore\Gateway\CacheGateway;
use BlueFission\Data\Storage\Storage;
use BlueFission\Security\Hash;
use BlueFission\Services\Request;
use BlueFission\Str;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CacheGatewayTest extends TestCase
{
    public function testCacheKeyPreservesLegacyPayloadSignatureThroughHashHelper(): void
    {
        $request = new class extends Request {
            public function uri(): string
            {
                return '/reports/monthly';
            }

            public function data(): array
            {
                return ['period' => '2026-07', 'format' => 'json'];
            }
        };

        $gateway = new CacheGateway(new Storage(), 60);
        $method = new ReflectionMethod($gateway, 'generateCacheKey');
        $method->setAccessible(true);

        $payload = Str::make('/reports/monthly')
            ->append(':')
            ->append(serialize(['period' => '2026-07', 'format' => 'json']))
            ->val();

        $this->assertSame(
            Hash::value($payload, 'md5'),
            $method->invoke($gateway, $request)
        );
    }
}
