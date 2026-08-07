<?php

namespace BlueFission\Tests\BlueCore\Business\Managers;

use BlueFission\BlueCore\Business\Managers\DatasourceManager;
use BlueFission\Utils\File;
use PHPUnit\Framework\TestCase;

class DatasourceManagerClassResolutionTest extends TestCase
{
    public function testNamespacedAndLegacyComponentClassesAreResolved(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-datasource-components-' . uniqid();
        $manager = new ClassResolvingDatasourceManager();
        $namespacedFile = $root . DIRECTORY_SEPARATOR . 'CreateRecords.php';
        $legacyFile = $root . DIRECTORY_SEPARATOR . 'LegacySeeder.php';
        $missingClassFile = $root . DIRECTORY_SEPARATOR . 'functions.php';

        File::ensureFile(
            $namespacedFile,
            "<?php\nnamespace Vendor\\Package\\Datasource;\nclass CreateRecords {}",
            true
        );
        File::ensureFile($legacyFile, "<?php\nclass LegacySeeder {}", true);
        File::ensureFile($missingClassFile, "<?php\nfunction helper() {}", true);

        $this->assertSame(
            'Vendor\\Package\\Datasource\\CreateRecords',
            $manager->className($namespacedFile)
        );
        $this->assertSame('LegacySeeder', $manager->className($legacyFile));
        $this->assertNull($manager->className($missingClassFile));
        $this->assertNull($manager->className($root . DIRECTORY_SEPARATOR . 'missing.php'));
    }
}

class ClassResolvingDatasourceManager extends DatasourceManager
{
    public function __construct()
    {
    }

    public function className(string $file): ?string
    {
        return $this->findClassName($file);
    }
}
