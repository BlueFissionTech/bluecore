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

    public function testPlanPreservesOpaqueHostContextWithoutUniversalFields(): void
    {
        $executor = new InstallationExecutor();
        $context = [
            'host_defined' => ['mode' => 'custom'],
            'capabilities' => ['registration'],
        ];

        $plan = $executor->prepare($context, 'opaque-plan');

        $this->assertSame('opaque-plan', $plan->id());
        $this->assertSame(InstallationPlan::STATUS_READY, $plan->status());
        $this->assertSame($context, $plan->context());
        $this->assertSame([], $plan->diagnostics());
        $this->assertTrue($plan->is($plan->behaviorState()));
        $this->assertArrayNotHasKey('project_name', $plan->toArray());
        $this->assertArrayNotHasKey('permissions', $plan->toArray());
        $this->assertArrayNotHasKey('skips', $plan->toArray());
    }

    public function testHostValidatorOwnsContextRequirements(): void
    {
        $executor = (new InstallationExecutor())->validator(
            fn (array $context): array => Arr::hasKey($context, 'definition')
                ? []
                : [[
                    'field' => 'definition',
                    'reason' => 'required_by_host',
                    'message' => 'A host definition is required.',
                ]]
        );

        $invalid = $executor->prepare([]);
        $valid = $executor->prepare(['definition' => ['type' => 'application']]);

        $this->assertSame(InstallationPlan::STATUS_DRAFT, $invalid->status());
        $this->assertSame('required_by_host', $invalid->diagnostics()[0]['reason']);
        $this->assertSame(InstallationPlan::STATUS_READY, $valid->status());
    }

    public function testApprovalGateIsOptionalAndStoresOpaqueHostEvidence(): void
    {
        $unguardedCalls = 0;
        $unguarded = (new InstallationExecutor())
            ->stage('operation', function () use (&$unguardedCalls): bool {
                $unguardedCalls++;
                return true;
            });
        $unguardedResult = $unguarded->execute($unguarded->prepare());

        $this->assertTrue($unguardedResult['ok']);
        $this->assertSame(1, $unguardedCalls);

        $guardedCalls = 0;
        $guarded = (new InstallationExecutor())
            ->requireApproval()
            ->stage('operation', function () use (&$guardedCalls): bool {
                $guardedCalls++;
                return true;
            }, ['owner' => 'host']);
        $plan = $guarded->prepare();

        try {
            $guarded->execute($plan);
            $this->fail('Execution should require host approval when the gate is enabled.');
        } catch (\LogicException $exception) {
            $this->assertSame('Installation execution requires host approval.', $exception->getMessage());
        }
        $this->assertSame(0, $guardedCalls);
        $this->assertSame(['name' => 'operation', 'metadata' => ['owner' => 'host']], $guarded->stages()[0]);

        $plan->approve(['decision_id' => 'host-decision-1']);
        $this->assertSame(
            ['decision_id' => 'host-decision-1'],
            Arr::getPath($plan->toArray(), 'approval_evidence')
        );
        $result = $guarded->execute($plan);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $guardedCalls);
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
            })
            ->stage('migrations', function () use (&$migrationCalls): array {
                $migrationCalls++;
                return $migrationCalls === 1
                    ? ['ok' => false, 'error' => 'temporary failure']
                    : ['ok' => true, 'changed' => true, 'evidence' => ['applied' => 2]];
            });

        $plan = $executor->prepare(['host_defined' => true]);
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

    public function testHostStageCanReturnStructuredSkipEvidence(): void
    {
        $executor = (new InstallationExecutor())->stage('optional', fn (): array => [
            'ok' => true,
            'changed' => false,
            'skipped' => true,
            'evidence' => ['reason' => 'host_policy'],
        ]);

        $result = $executor->execute($executor->prepare());

        $this->assertTrue($result['ok']);
        $this->assertTrue(Arr::getPath($result, 'outcomes.optional.skipped'));
        $this->assertSame('host_policy', Arr::getPath($result, 'outcomes.optional.evidence.reason'));
    }

    public function testResumeRequiresAnExplicitCheckpointStore(): void
    {
        $this->expectException(\LogicException::class);

        (new InstallationExecutor())->resume('missing');
    }

    private function executor(): InstallationExecutor
    {
        return new InstallationExecutor(
            new JsonInstallationCheckpointStore($this->checkpointDirectory)
        );
    }
}
