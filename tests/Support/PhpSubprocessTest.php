<?php

namespace BlueFission\Tests\Support;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PhpSubprocessTest extends TestCase
{
    private string|false $originalProfile;
    private ?string $profile = null;

    protected function setUp(): void
    {
        $this->originalProfile = getenv('BLUECORE_TEST_PHP_INI');
        putenv('BLUECORE_TEST_PHP_INI');
    }

    protected function tearDown(): void
    {
        putenv($this->originalProfile === false
            ? 'BLUECORE_TEST_PHP_INI'
            : 'BLUECORE_TEST_PHP_INI=' . $this->originalProfile);
        if ($this->profile !== null) {
            unlink($this->profile);
        }
    }

    public function testDefaultCommandPreservesExistingBehavior(): void
    {
        $this->assertSame(escapeshellarg(PHP_BINARY), PhpSubprocess::command());
    }

    public function testEmptyProfileUsesTheDefaultCommand(): void
    {
        putenv('BLUECORE_TEST_PHP_INI=');
        $this->assertSame(escapeshellarg(PHP_BINARY), PhpSubprocess::command());
    }

    public function testExplicitProfileIsPassedAsOneQuotedArgument(): void
    {
        $this->profile = __DIR__ . '/php test profile ' . bin2hex(random_bytes(8)) . '.ini';
        $this->assertNotFalse(file_put_contents($this->profile, "; synthetic test profile\n"));
        putenv('BLUECORE_TEST_PHP_INI=' . $this->profile);
        $this->assertSame(
            escapeshellarg(PHP_BINARY) . ' -c ' . escapeshellarg($this->profile),
            PhpSubprocess::command()
        );
    }

    public function testMissingProfileIsRejectedInsteadOfSilentlyChangingRuntime(): void
    {
        putenv('BLUECORE_TEST_PHP_INI=' . __DIR__ . '/absent-test-profile.ini');
        $this->expectException(InvalidArgumentException::class);
        PhpSubprocess::command();
    }
}
