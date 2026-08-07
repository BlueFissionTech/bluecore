<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\BlueCore\Business\Managers\DatasourceManager;
use BlueFission\Connections\Database\MySQLLink;
use BlueFission\Data\Storage\Storage;
use BlueFission\IObj;
use PHPUnit\Framework\TestCase;

class DatasourceManagerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-datasource-manager-tests');
        }

        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', APP_ROOT);
        }
    }

    public function testMigrationStorageIsConfiguredBeforeActivation(): void
    {
        $link = new TrackingMySQLLink();
        $storage = new TrackingMigrationStorage();

        new DatasourceManager($link, $storage);

        $this->assertSame(1, $link->openCount);
        $this->assertSame(1, $storage->activationCount);
        $this->assertSame('migrations', $storage->nameAtActivation);
    }
}

class TrackingMySQLLink extends MySQLLink
{
    public int $openCount = 0;

    public function open(): IObj
    {
        $this->openCount++;

        return $this;
    }
}

class TrackingMigrationStorage extends Storage
{
    public int $activationCount = 0;
    public mixed $nameAtActivation = null;

    public function activate(): IObj
    {
        $this->activationCount++;
        $this->nameAtActivation = $this->config('name');

        return $this;
    }
}
