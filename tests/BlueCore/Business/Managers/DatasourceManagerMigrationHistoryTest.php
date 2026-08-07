<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\BlueCore\Business\Managers\DatasourceManager;
use PHPUnit\Framework\TestCase;

class DatasourceManagerMigrationHistoryTest extends TestCase
{
    public function testSuccessfulMigrationsAreIgnoredAcrossIterationsAndBatches(): void
    {
        $manager = new MigrationHistoryDatasourceManager();
        $iteration = 0;

        $names = $manager->history([
            ['name' => '001_core.php', 'batch' => 'core', 'iteration' => 1, 'status' => 2],
            ['name' => '002_users.php', 'batch' => 'core', 'iteration' => 1, 'status' => 2],
            ['name' => '003_retry.php', 'batch' => 'extension', 'iteration' => 2, 'status' => 3],
            (object)['name' => '004_extension.php', 'batch' => 'extension', 'iteration' => 3, 'status' => 2],
            ['name' => '001_core.php', 'batch' => 'replay', 'iteration' => 3, 'status' => 2],
        ], $iteration);

        $this->assertSame(3, $iteration);
        $this->assertSame([
            '001_core.php',
            '002_users.php',
            '004_extension.php',
        ], $names);
        $this->assertNotContains('003_retry.php', $names);
    }

    public function testEmptyHistoryPreservesTheInitialIteration(): void
    {
        $manager = new MigrationHistoryDatasourceManager();
        $iteration = 0;

        $this->assertSame([], $manager->history([], $iteration));
        $this->assertSame(0, $iteration);
    }
}

class MigrationHistoryDatasourceManager extends DatasourceManager
{
    public function __construct()
    {
    }

    public function history(array $rows, int &$iteration): array
    {
        return $this->migrationHistory($rows, $iteration);
    }
}
