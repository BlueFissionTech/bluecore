<?php

namespace BlueFission\BlueCore\Installation;

use BlueFission\Arr;
use BlueFission\BlueCore\Contracts\IInstallationCheckpointStore;
use BlueFission\Collections\Collection;
use BlueFission\Flag;
use BlueFission\Func;
use BlueFission\Str;
use BlueFission\Val;

class InstallationExecutor
{
    private Collection $stages;

    public function __construct(private IInstallationCheckpointStore $checkpoints)
    {
        $this->stages = new Collection();
    }

    public function stage(string $name, callable|Func $operation, array $permissions = []): self
    {
        $name = Str::make($name)->trim()->val();
        if (Val::isEmpty($name)) {
            throw new \InvalidArgumentException('Installation stage name cannot be empty.');
        }

        $this->stages->add([
            'name' => $name,
            'operation' => $operation instanceof Func ? $operation : Func::make($operation),
            'permissions' => Arr::make($permissions)->values()->unique()->toArray(),
        ], $name);

        return $this;
    }

    public function prepare(array $packet): InstallationPlan
    {
        $plan = new InstallationPlan($packet);
        $permissions = Arr::make([]);
        foreach ($this->stages as $stage) {
            $stage = Arr::make($stage);
            $permissions->merge($stage->get('permissions', []));
        }
        $plan->requestPermissions($permissions->values()->unique()->toArray());
        $this->checkpoints->save($plan->id(), $plan->toArray());

        return $plan;
    }

    public function resume(string $planId): ?InstallationPlan
    {
        $checkpoint = $this->checkpoints->load($planId);

        return Val::isNull($checkpoint) ? null : InstallationPlan::restore($checkpoint);
    }

    public function execute(InstallationPlan $plan): array
    {
        $plan->start();
        $this->checkpoints->save($plan->id(), $plan->toArray());

        foreach ($this->stages as $stage) {
            $stage = Arr::make($stage);
            $name = (string)$stage->get('name');
            if ($plan->completedStage($name)) {
                continue;
            }

            if ($plan->skipped($name)) {
                $outcome = $this->outcome($name, [
                    'ok' => true,
                    'changed' => false,
                    'skipped' => true,
                    'evidence' => ['reason' => 'explicit_skip'],
                ]);
                $plan->checkpoint($name, $outcome);
                $this->checkpoints->save($plan->id(), $plan->toArray());
                continue;
            }

            $operation = $stage->get('operation');
            try {
                $raw = $operation instanceof Func ? $operation->call($plan) : null;
                $outcome = $this->outcome($name, $raw);
            } catch (\Throwable $exception) {
                $outcome = $this->outcome($name, [
                    'ok' => false,
                    'changed' => false,
                    'error' => $exception->getMessage(),
                    'exception' => $exception::class,
                    'nextAction' => 'retry_'.$name,
                ]);
            }

            $plan->checkpoint($name, $outcome);
            if (Flag::make(Arr::getPath($outcome, 'ok', false))->isFalse()) {
                $plan->fail($name, $outcome);
                $this->checkpoints->save($plan->id(), $plan->toArray());

                return $this->result($plan, false);
            }

            $this->checkpoints->save($plan->id(), $plan->toArray());
        }

        $plan->complete();
        $this->checkpoints->save($plan->id(), $plan->toArray());

        return $this->result($plan, true);
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
