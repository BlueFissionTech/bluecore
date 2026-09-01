<?php

namespace BlueFission\BlueCore\Installation;

use BlueFission\Arr;
use BlueFission\BlueCore\Contracts\IInstallationCheckpointStore;
use BlueFission\BlueCore\Hooks\DispatchesLifecycleHooks;
use BlueFission\BlueCore\Hooks\LifecycleFailure;
use BlueFission\Collections\Collection;
use BlueFission\Flag;
use BlueFission\Func;
use BlueFission\Str;
use BlueFission\Val;

class InstallationExecutor
{
    use DispatchesLifecycleHooks;

    public const HOOK_PREPARE_AFTER = 'bluecore.installation.prepare.after';
    public const HOOK_RESUME_AFTER = 'bluecore.installation.resume.after';
    public const HOOK_EXECUTE_BEFORE = 'bluecore.installation.execute.before';
    public const HOOK_EXECUTE_AFTER = 'bluecore.installation.execute.after';
    public const HOOK_EXECUTE_FAILED = 'bluecore.installation.execute.failed';
    public const HOOK_STAGE_BEFORE = 'bluecore.installation.stage.before';
    public const HOOK_STAGE_AFTER = 'bluecore.installation.stage.after';
    public const HOOK_STAGE_FAILED = 'bluecore.installation.stage.failed';
    public const HOOK_CHECKPOINTED = 'bluecore.installation.checkpoint.after';

    private Collection $stages;
    private ?Func $validator = null;
    private bool $approvalRequired = false;

    public function __construct(private ?IInstallationCheckpointStore $checkpoints = null)
    {
        $this->stages = new Collection();
    }

    public function validator(callable|Func $validator): self
    {
        $this->validator = $validator instanceof Func ? $validator : Func::make($validator);

        return $this;
    }

    public function requireApproval(bool $required = true): self
    {
        $this->approvalRequired = $required;

        return $this;
    }

    public function stage(string $name, callable|Func $operation, array $metadata = []): self
    {
        $name = Str::make($name)->trim()->val();
        if (Val::isEmpty($name)) {
            throw new \InvalidArgumentException('Installation stage name cannot be empty.');
        }

        $this->stages->add([
            'name' => $name,
            'operation' => $operation instanceof Func ? $operation : Func::make($operation),
            'metadata' => Arr::make($metadata)->toArray(),
        ], $name);

        return $this;
    }

    public function stages(): array
    {
        $definitions = new Collection();
        foreach ($this->stages as $stage) {
            $record = Arr::make($stage);
            $definitions->add([
                'name' => $record->get('name'),
                'metadata' => Arr::make($record->get('metadata', []))->toArray(),
            ]);
        }

        return $definitions->toArray();
    }

    public function prepare(array $context = [], ?string $planId = null): InstallationPlan
    {
        $plan = new InstallationPlan($context, $planId);
        if (Val::isNotNull($this->validator)) {
            $plan->applyDiagnostics($this->validate($context));
        }
        $this->save($plan);
        $this->dispatchLifecycleAction(self::HOOK_PREPARE_AFTER, [
            $this->planSummary($plan),
            $this,
        ]);

        return $plan;
    }

    public function resume(string $planId): ?InstallationPlan
    {
        if (Val::isNull($this->checkpoints)) {
            throw new \LogicException('A checkpoint store is required to resume an installation plan.');
        }

        $checkpoint = $this->checkpoints->load($planId);
        $plan = Val::isNull($checkpoint) ? null : InstallationPlan::restore($checkpoint);

        if (Val::isNotNull($plan)) {
            $this->dispatchLifecycleAction(self::HOOK_RESUME_AFTER, [
                $this->planSummary($plan),
                $this,
            ]);
        }

        return $plan;
    }

    public function execute(InstallationPlan $plan): array
    {
        try {
            $plan->start($this->approvalRequired);
        } catch (\Throwable $exception) {
            $this->dispatchInstallationFailure(
                self::HOOK_EXECUTE_FAILED,
                'execute',
                self::class,
                $exception
            );

            throw $exception;
        }

        $this->save($plan);
        $this->dispatchLifecycleAction(self::HOOK_EXECUTE_BEFORE, [
            $this->planSummary($plan),
            $this,
        ]);

        foreach ($this->stages as $stage) {
            $stage = Arr::make($stage);
            $name = (string)$stage->get('name');
            if ($plan->completedStage($name)) {
                continue;
            }

            $operation = $stage->get('operation');
            $stageFailure = null;
            $this->dispatchLifecycleAction(self::HOOK_STAGE_BEFORE, [
                $this->stageSummary($plan, $name),
                $this,
            ]);

            try {
                $raw = $operation instanceof Func ? $operation->call($plan) : null;
                $outcome = $this->outcome($name, $raw);
            } catch (\Throwable $exception) {
                $stageFailure = $exception;
                $outcome = $this->outcome($name, [
                    'ok' => false,
                    'changed' => false,
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'nextAction' => 'retry_'.$name,
                ]);
            }

            if (Flag::make(Arr::getPath($outcome, 'ok', false))->isFalse()) {
                $stageFailure ??= new \RuntimeException(
                    (string)Arr::getPath($outcome, 'error', 'Installation stage failed.')
                );
                $this->dispatchInstallationFailure(
                    self::HOOK_STAGE_FAILED,
                    'stage',
                    $name,
                    $stageFailure
                );
            } else {
                $this->dispatchLifecycleAction(self::HOOK_STAGE_AFTER, [
                    $this->outcomeSummary($plan, $outcome),
                    $this,
                ]);
            }

            $plan->checkpoint($name, $outcome);
            $this->dispatchLifecycleAction(self::HOOK_CHECKPOINTED, [
                $this->outcomeSummary($plan, $outcome),
                $this,
            ]);
            if (Flag::make(Arr::getPath($outcome, 'ok', false))->isFalse()) {
                $plan->fail($name, $outcome);
                $this->save($plan);
                $result = $this->result($plan, false);
                $this->dispatchInstallationFailure(
                    self::HOOK_EXECUTE_FAILED,
                    'execute',
                    self::class,
                    $stageFailure ?? new \RuntimeException('Installation execution failed.')
                );

                return $result;
            }

            $this->save($plan);
        }

        $plan->complete();
        $this->save($plan);
        $result = $this->result($plan, true);
        $this->dispatchLifecycleAction(self::HOOK_EXECUTE_AFTER, [
            $this->planSummary($plan),
            $this,
        ]);

        return $result;
    }

    private function planSummary(InstallationPlan $plan): Arr
    {
        $data = $plan->toArray();

        return Arr::make([
            'plan_id' => $plan->id(),
            'status' => $plan->status(),
            'diagnostic_count' => Arr::size($plan->diagnostics()),
            'checkpoint_count' => Arr::size(Arr::getPath($data, 'checkpoints', [])),
        ]);
    }

    private function stageSummary(InstallationPlan $plan, string $stage): Arr
    {
        return Arr::make([
            'plan_id' => $plan->id(),
            'status' => $plan->status(),
            'stage' => $stage,
        ]);
    }

    private function outcomeSummary(InstallationPlan $plan, array $outcome): Arr
    {
        return Arr::make([
            'plan_id' => $plan->id(),
            'status' => $plan->status(),
            'stage' => Arr::getPath($outcome, 'stage'),
            'ok' => Flag::parseBool(Arr::getPath($outcome, 'ok', false)),
            'changed' => Flag::parseBool(Arr::getPath($outcome, 'changed', false)),
            'skipped' => Flag::parseBool(Arr::getPath($outcome, 'skipped', false)),
            'next_action' => Arr::getPath($outcome, 'nextAction'),
            'exception_type' => Arr::getPath($outcome, 'exception'),
        ]);
    }

    private function dispatchInstallationFailure(
        string $hook,
        string $operation,
        string $dispatcher,
        \Throwable $exception
    ): void {
        $this->dispatchLifecycleAction($hook, [
            new LifecycleFailure($hook, $operation, $dispatcher, $exception),
        ]);
    }

    private function validate(array $context): array
    {
        try {
            $result = $this->validator?->call($context);
        } catch (\Throwable $exception) {
            return [[
                'reason' => 'validation_exception',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]];
        }

        if (Val::isNull($result) || Flag::make($result)->isTrue()) {
            return [];
        }
        if (Flag::make($result)->isFalse()) {
            return [[
                'reason' => 'invalid_context',
                'message' => 'The host rejected the installation context.',
            ]];
        }
        if (Str::is($result)) {
            return [['reason' => 'invalid_context', 'message' => $result]];
        }
        if (Arr::is($result)) {
            return Arr::hasKey($result, 'message')
                ? [Arr::make($result)->toArray()]
                : Arr::make($result)->values()->toArray();
        }

        throw new \UnexpectedValueException('Installation validators must return null, bool, string, or diagnostics.');
    }

    private function save(InstallationPlan $plan): void
    {
        if (Val::isNotNull($this->checkpoints)) {
            $this->checkpoints->save($plan->id(), $plan->toArray());
        }
    }

    private function outcome(string $stage, mixed $raw): array
    {
        if (Flag::make($raw)->isBool()) {
            $raw = ['ok' => $raw, 'changed' => $raw];
        } elseif (Val::isNull($raw)) {
            $raw = ['ok' => true, 'changed' => false];
        } elseif (!Arr::is($raw)) {
            $raw = [
                'ok' => true,
                'changed' => true,
                'evidence' => ['result' => $raw],
            ];
        }

        $outcome = Arr::make([
            'stage' => $stage,
            'ok' => false,
            'changed' => false,
            'skipped' => false,
            'evidence' => [],
            'error' => null,
            'exception' => null,
            'nextAction' => null,
        ])->merge($raw);

        if (Flag::make($outcome->get('ok'))->isFalse()
            && Val::isEmpty($outcome->get('nextAction'))) {
            $outcome->set('nextAction', 'retry_'.$stage);
        }

        return $outcome->toArray();
    }

    private function result(InstallationPlan $plan, bool $ok): array
    {
        $data = $plan->toArray();

        return Arr::make([
            'ok' => $ok,
            'plan_id' => $plan->id(),
            'status' => $plan->status(),
            'outcomes' => Arr::getPath($data, 'outcomes', []),
            'failure' => Arr::getPath($data, 'failure'),
            'nextAction' => $ok ? null : Arr::getPath($data, 'failure.nextAction'),
        ])->toArray();
    }
}
