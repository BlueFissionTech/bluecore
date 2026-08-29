<?php

namespace BlueFission\Tests\BlueCore\Installation;

use BlueFission\Arr;
use BlueFission\BlueCore\Installation\InstallationExecutor;
use BlueFission\BlueCore\Installation\InstallationPlan;
use BlueFission\BlueCore\Installation\JsonInstallationCheckpointStore;
use BlueFission\Data\FileSystem;
use BlueFission\Str;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

class InstallationExecutorTest extends TestCase
{
    private string $checkpointDirectory;

    protected function setUp(): void
    {
        $this->checkpointDirectory = Path::ensureDir(
            Path::normalize(
                sys_get_temp_dir().DIRECTORY_SEPARATOR.'bluecore-installation-'.Str::rand('', 10)
            )
        );
    }

    protected function tearDown(): void
    {
        if (!FileSystem::directoryExists($this->checkpointDirectory)) {
            return;
        }

        foreach (glob($this->checkpointDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
            if (FileSystem::fileExists($path)) {
                File::deletePath($path);
            }
        }
        rmdir($this->checkpointDirectory);
    }

    public function testPlanMakesDefaultsSkipsAndValidationExplicit(): void
    {
        $executor = $this->executor();
        $invalid = $executor->prepare([]);

        $this->assertSame(InstallationPlan::STATUS_DRAFT, $invalid->status());
        $this->assertSame('required', $invalid->diagnostics()[0]['reason']);

        $plan = $executor->prepare([
            'project_name' => 'Materia Host',
            'values' => ['environment' => 'production'],
            'defaults' => ['region' => 'global', 'environment' => 'local'],
            'skips' => ['population'],
        ]);

        $this->assertSame(InstallationPlan::STATUS_REVIEW_READY, $plan->status());
        $this->assertSame('production', $plan->values()['environment']);
        $this->assertSame('global', $plan->values()['region']);
        $this->assertTrue($plan->skipped('population'));
        $this->assertTrue($plan->is($plan->behaviorState()));
    }

    public function testExecutionRequiresPermissionReviewBeforeSideEffects(): void
    {
        $calls = 0;
        $executor = $this->executor()
            ->stage('packages', function () use (&$calls): array {
                $calls++;
                return ['ok' => true, 'changed' => true];
            }, ['write_project']);
        $plan = $executor->prepare(['project_name' => 'Guarded Host']);

        try {
            $executor->execute($plan);
            $this->fail('Execution should require approval.');
        } catch (\LogicException $exception) {
            $this->assertSame('Installation execution requires explicit approval.', $exception->getMessage());
        }
        $this->assertSame(0, $calls);

        $this->expectException(\LogicException::class);
        $plan->approve([]);
    }

    public function testCompletedStagesAreNotRepeatedDuringCheckpointResume(): void
    {
        $packageCalls = 0;
        $migrationCalls = 0;
        $executor = $this->executor()
            ->stage('packages', function () use (&$packageCalls): array {
                $packageCalls++;
                return [
                    'ok' => true,
                    'changed' => true,
                    'evidence' => ['owner' => 'package_installer'],
                ];
            }, ['write_project'])
            ->stage('migrations', function () use (&$migrationCalls): array {
                $migrationCalls++;
                return $migrationCalls === 1
                    ? ['ok' => false, 'error' => 'temporary failure']
                    : ['ok' => true, 'changed' => true, 'evidence' => ['applied' => 2]];
            }, ['write_database']);

        $plan = $executor->prepare(['project_name' => 'Resumable Host']);
        $plan->approve(['write_project', 'write_database']);
        $first = $executor->execute($plan);

        $this->assertFalse($first['ok']);
        $this->assertSame('retry_migrations', $first['nextAction']);
        $this->assertSame(1, $packageCalls);
        $this->assertSame(1, $migrationCalls);

        $resumed = $executor->resume($plan->id());
        $this->assertInstanceOf(InstallationPlan::class, $resumed);
        $resumed->retry();
        $second = $executor->execute($resumed);

        $this->assertTrue($second['ok']);
        $this->assertSame(InstallationPlan::STATUS_COMPLETED, $second['status']);
        $this->assertSame(1, $packageCalls);
        $this->assertSame(2, $migrationCalls);
        $this->assertSame(2, Arr::getPath($second, 'outcomes.migrations.evidence.applied'));
    }

    public function testExplicitlySkippedStageProducesStructuredEvidence(): void
    {
        $calls = 0;
        $executor = $this->executor()->stage('population', function () use (&$calls): bool {
            $calls++;
            return true;
        });
        $plan = $executor->prepare([
            'project_name' => 'Skip Host',
            'skips' => ['population'],
        ]);
        $plan->approve([]);

        $result = $executor->execute($plan);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $calls);
        $this->assertTrue(Arr::getPath($result, 'outcomes.population.skipped'));
        $this->assertSame(
            'explicit_skip',
            Arr::getPath($result, 'outcomes.population.evidence.reason')
        );
    }

    private function executor(): InstallationExecutor
    {
        return new InstallationExecutor(
            new JsonInstallationCheckpointStore($this->checkpointDirectory)
        );
    }
}
