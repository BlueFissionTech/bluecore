<?php

namespace BlueFission\Tests\Helpers;

use BlueFission\Arr;
use BlueFission\BlueCore\Hooks\HelperLifecycleHooks;
use BlueFission\DevElation as Dev;
use BlueFission\Str;
use BlueFission\System\Process;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

class ProductionErrorHandlerTest extends TestCase
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

    public function testProductionWarningIsLoggedWithoutResponseOutput(): void
    {
        $debugMode = getenv('DEBUG_MODE');
        $errorLog = ini_get('error_log');
        $testLog = Path::normalize(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-warning-' . Str::rand('', 10) . '.log');
        putenv('DEBUG_MODE=false');
        ini_set('error_log', $testLog);

        try {
            ob_start();
            customErrorHandler(E_WARNING, 'private warning', __FILE__, __LINE__);
            $response = ob_get_clean();

            $this->assertSame('', $response);
            $this->assertStringContainsString('private warning', File::readContents($testLog));
        } finally {
            ini_set('error_log', (string)$errorLog);
            $debugMode === false ? putenv('DEBUG_MODE') : putenv("DEBUG_MODE={$debugMode}");
            if ((new File())->exists($testLog)) {
                unlink($testLog);
            }
        }
    }

    public function testUnhandledCliExceptionExitsNonzeroWithoutResponseOutput(): void
    {
        $root = Path::normalize(dirname(__DIR__, 2));
        $fixture = Path::normalize($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'runtime-unhandled-exception.php');
        $errorLog = Path::normalize(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-error-' . Str::rand('', 10) . '.log');
        $command = Str::make(escapeshellarg(PHP_BINARY))
            ->append(' ')
            ->append(escapeshellarg($fixture))
            ->val();
        $process = new Process($command, $root, ['DEBUG_MODE' => 'false'], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $errorLog, 'a'],
        ]);

        $process->start();
        while ($process->status() === true) {
            usleep(10000);
        }
        $response = $process->output();
        $exitCode = $process->close();

        $this->assertNotSame(0, $exitCode);
        $this->assertSame('', $response);
        $this->assertStringContainsString('private exception', File::readContents($errorLog));

        unlink($errorLog);
    }

    public function testProductionWarningDispatchesSanitizedErrorSummary(): void
    {
        $debugMode = getenv('DEBUG_MODE');
        $errorLog = ini_get('error_log');
        $testLog = Path::normalize(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-hook-warning-' . Str::rand('', 10) . '.log');
        $summary = null;
        putenv('DEBUG_MODE=false');
        ini_set('error_log', $testLog);
        Dev::up();
        Dev::action(HelperLifecycleHooks::HOOK_ERROR_REPORTED, function (Arr $context) use (&$summary): void {
            $summary = $context;
        });

        try {
            customErrorHandler(E_WARNING, 'private warning', __FILE__, __LINE__);

            $this->assertInstanceOf(Arr::class, $summary);
            $this->assertSame('error', $summary['kind']);
            $this->assertSame(E_WARNING, $summary['code']);
            $this->assertFalse($summary->hasKey('message'));
            $this->assertFalse($summary->hasKey('file'));
        } finally {
            ini_set('error_log', (string)$errorLog);
            $debugMode === false ? putenv('DEBUG_MODE') : putenv("DEBUG_MODE={$debugMode}");
            if ((new File())->exists($testLog)) {
                unlink($testLog);
            }
        }
    }

    public function testSuppressedWarningIsNotReclassifiedAsFatalAtShutdown(): void
    {
        $result = $this->runShutdownFixture('suppressed-warning', false);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('completed', $result['output']);
        $this->assertStringNotContainsString('Fatal Error:', $result['errorLog']);
        $this->assertSame('', $result['fatalEvidence']);
    }

    public function testHandledWarningIsNotReclassifiedAsFatalAtShutdown(): void
    {
        $result = $this->runShutdownFixture('handled-warning', false);

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('completed', $result['output']);
        $this->assertStringContainsString('Error: [' . E_USER_WARNING . '] handled warning', $result['errorLog']);
        $this->assertStringNotContainsString('Fatal Error:', $result['errorLog']);
        $this->assertSame('', $result['fatalEvidence']);
    }

    public function testProductionFatalShutdownReportsDiagnosticAndLifecycleEvidenceOnce(): void
    {
        $result = $this->runShutdownFixture('fatal', false);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertSame('', $result['output']);
        $this->assertSame(1, substr_count($result['errorLog'], 'Fatal Error: [' . E_ERROR . ']'));
        $this->assertStringContainsString('Call to undefined function', $result['errorLog']);
        $this->assertStringContainsString('"kind":"fatal"', $result['fatalEvidence']);
        $this->assertStringContainsString('"code":' . E_ERROR, $result['fatalEvidence']);
        $this->assertStringContainsString('"debug":false', $result['fatalEvidence']);
    }

    public function testDebugFatalShutdownAddsCliOutputWithoutDuplicatingLifecycleEvidence(): void
    {
        $result = $this->runShutdownFixture('fatal', true);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertStringContainsString(
            'Fatal Error: Uncaught Error: Call to undefined function',
            $result['output']
        );
        $this->assertSame(1, substr_count($result['errorLog'], 'Fatal Error: [' . E_ERROR . ']'));
        $this->assertStringContainsString('"kind":"fatal"', $result['fatalEvidence']);
        $this->assertStringContainsString('"code":' . E_ERROR, $result['fatalEvidence']);
        $this->assertStringContainsString('"debug":true', $result['fatalEvidence']);
    }

    /**
     * @return array{exitCode: int, output: string, errorLog: string, fatalEvidence: string}
     */
    private function runShutdownFixture(string $mode, bool $debug): array
    {
        $root = Path::normalize(dirname(__DIR__, 2));
        $fixture = Path::normalize(
            $root
            . DIRECTORY_SEPARATOR
            . 'tests'
            . DIRECTORY_SEPARATOR
            . 'Fixtures'
            . DIRECTORY_SEPARATOR
            . 'runtime-shutdown-error.php'
        );
        $token = Str::rand('', 10);
        $errorLog = Path::normalize(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . "bluecore-shutdown-{$token}.log"
        );
        $evidencePath = Path::normalize(
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . "bluecore-shutdown-evidence-{$token}.json"
        );
        $command = Str::make(escapeshellarg(PHP_BINARY))
            ->append(' -d ')
            ->append(escapeshellarg('display_errors=' . ($debug ? '1' : '0')))
            ->append(' ')
            ->append(escapeshellarg($fixture))
            ->append(' ')
            ->append(escapeshellarg($mode))
            ->append(' ')
            ->append(escapeshellarg($evidencePath))
            ->val();
        $process = new Process($command, $root, ['DEBUG_MODE' => $debug ? 'true' : 'false'], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $errorLog, 'a'],
        ]);

        try {
            $process->start();
            while ($process->status() === true) {
                usleep(10000);
            }

            $output = $process->output();
            $exitCode = $process->close();
            $errorOutput = (new File())->exists($errorLog) ? File::readContents($errorLog) : '';
            $fatalEvidence = (new File())->exists($evidencePath) ? File::readContents($evidencePath) : '';

            return Arr::make([
                'exitCode' => $exitCode,
                'output' => $output,
                'errorLog' => $errorOutput,
                'fatalEvidence' => $fatalEvidence,
            ])->toArray();
        } finally {
            File::deletePath($errorLog);
            File::deletePath($evidencePath);
        }
    }

    private function resetDevElation(): void
    {
        Dev::down();

        foreach (['_filters', '_actions', '_listeners', '_config'] as $propertyName) {
            $property = new \ReflectionProperty(Dev::class, $propertyName);
            $property->setValue(null, []);
        }
    }
}
