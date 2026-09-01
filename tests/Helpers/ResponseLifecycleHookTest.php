<?php

namespace BlueFission\Tests\Helpers;

use BlueFission\Str;
use BlueFission\System\Process;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

class ResponseLifecycleHookTest extends TestCase
{
    public function testResponseFilterActionsAndPreparedEventUseTypedSanitizedContexts(): void
    {
        [$output, $exitCode] = $this->runFixture('success');

        $this->assertSame(0, $exitCode);
        $this->assertStringStartsWith('filter|before:202|after:202|prepared:202|', $output);
        $this->assertStringContainsString('"status":"filtered"', $output);
    }

    public function testResponseFilterRejectsInvalidTypesAndDispatchesSanitizedFailure(): void
    {
        [$output, $exitCode] = $this->runFixture('invalid');

        $this->assertSame(0, $exitCode);
        $this->assertSame('failed:UnexpectedValueException|caught', $output);
    }

    public function testResponseHooksRemainInactiveUntilDevElationIsEnabled(): void
    {
        [$output, $exitCode] = $this->runFixture('inactive');

        $this->assertSame(0, $exitCode);
        $this->assertStringStartsNotWith('filter|', $output);
        $this->assertStringContainsString('"status":"inactive"', $output);
    }

    private function runFixture(string $mode): array
    {
        $root = Path::normalize(dirname(__DIR__, 2));
        $fixture = Path::normalize(
            $root
            . DIRECTORY_SEPARATOR
            . 'tests'
            . DIRECTORY_SEPARATOR
            . 'Fixtures'
            . DIRECTORY_SEPARATOR
            . 'response-lifecycle-hooks.php'
        );
        $command = Str::make(escapeshellarg(PHP_BINARY))
            ->append(' ')
            ->append(escapeshellarg($fixture))
            ->append(' ')
            ->append(escapeshellarg($mode))
            ->val();
        $process = new Process($command, $root);

        $process->start();
        while ($process->status() === true) {
            usleep(10000);
        }

        return [$process->output(), $process->close()];
    }
}
