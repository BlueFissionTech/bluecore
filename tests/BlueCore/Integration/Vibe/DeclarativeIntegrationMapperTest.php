<?php

namespace BlueFission\Tests\BlueCore\Integration\Vibe;

use BlueFission\BlueCore\Gateway\AuthenticationGateway;
use BlueFission\BlueCore\Gateway\CorsGateway;
use BlueFission\BlueCore\Gateway\DynamicGateway;
use BlueFission\BlueCore\Integration\Vibe\DeclarativeIntegrationMapper;
use BlueFission\BlueCore\Model\ModelSQLite;
use BlueFission\BlueCore\Model\ModelSql;
use PHPUnit\Framework\TestCase;

class DeclarativeIntegrationMapperTest extends TestCase
{
    public function testMapsDataDeclarationsToBlueCoreModels(): void
    {
        $mapped = (new DeclarativeIntegrationMapper())->map([
            'data' => [
                'Article' => [
                    'fields' => ['title', 'body'],
                ],
                'Account' => [
                    'backend' => 'mysql',
                    'table' => 'accounts',
                    'key' => 'account_id',
                    'fields' => ['name'],
                ],
            ],
        ]);

        $this->assertSame(ModelSQLite::class, $mapped['models'][0]['class']);
        $this->assertSame('articles', $mapped['models'][0]['table']);
        $this->assertSame(['article_id', 'title', 'body'], $mapped['models'][0]['fields']);
        $this->assertSame(ModelSql::class, $mapped['models'][1]['class']);
        $this->assertSame('accounts', $mapped['models'][1]['table']);
    }

    public function testMapsModuleDeclarationsToGatewayClasses(): void
    {
        $mapped = (new DeclarativeIntegrationMapper())->map([
            '@mod' => [
                'auth' => [],
                'cors' => [],
                'custom' => ['type' => 'unknown'],
            ],
        ]);

        $this->assertSame(AuthenticationGateway::class, $mapped['gateways'][0]['class']);
        $this->assertSame(CorsGateway::class, $mapped['gateways'][1]['class']);
        $this->assertSame(DynamicGateway::class, $mapped['gateways'][2]['class']);
    }

    public function testMapsApplicationServicesBindingsAndDelegates(): void
    {
        $mapped = (new DeclarativeIntegrationMapper())->map((object)[
            'app' => [
                'services' => ['cache' => 'CacheService'],
                'bindings' => ['repository.users' => 'UserRepository'],
                'delegates' => ['boot' => 'BootDelegate'],
            ],
        ]);

        $this->assertSame('CacheService', $mapped['app']['services']['cache']);
        $this->assertSame('UserRepository', $mapped['app']['bindings']['repository.users']);
        $this->assertSame('BootDelegate', $mapped['app']['delegates']['boot']);
    }
}
