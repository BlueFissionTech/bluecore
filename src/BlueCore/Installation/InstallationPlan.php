<?php

namespace BlueFission\BlueCore\Installation;

use BlueFission\Arr;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Collections\Collection;
use BlueFission\Date;
use BlueFission\Flag;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

class InstallationPlan extends Obj
{
    public const VERSION = 1;
    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    private const BEHAVIORS = [
        self::STATUS_DRAFT => 'IsInstallationDraft',
        self::STATUS_READY => 'IsInstallationReady',
        self::STATUS_APPROVED => 'IsInstallationApproved',
        self::STATUS_EXECUTING => 'IsInstallationExecuting',
        self::STATUS_COMPLETED => 'IsInstallationCompleted',
        self::STATUS_FAILED => 'IsInstallationFailed',
    ];

    public function __construct(array $context = [], ?string $id = null)
    {
        parent::__construct();

        $planId = Str::make((string)$id)->trim()->val();
        if (Val::isEmpty($planId)) {
            $planId = Str::make('installation-')->append(Str::rand('', 16))->val();
        }

        $now = Date::now()->val();
        $this->_data = Arr::make([
            'id' => $planId,
            'version' => self::VERSION,
            'context' => Arr::make($context)->toArray(),
            'diagnostics' => [],
            'status' => self::STATUS_READY,
            'approval_evidence' => [],
            'checkpoints' => [],
            'outcomes' => [],
            'created_at' => $now,
            'updated_at' => $now,
            'approved_at' => null,
            'completed_at' => null,
            'failure' => null,
        ]);

        $this->transition(self::STATUS_READY, false);
    }

    protected function init()
    {
        parent::init();
        foreach (self::BEHAVIORS as $behavior) {
            $this->behavior(new State($behavior));
        }
    }

    public static function restore(array $checkpoint): self
    {
        $version = (int)Arr::getPath($checkpoint, 'version', self::VERSION);
        if ($version !== self::VERSION) {
            throw new \InvalidArgumentException('The installation checkpoint version is not supported.');
        }

        $plan = new self(
            Arr::make(Arr::getPath($checkpoint, 'context', []))->toArray(),
            (string)Arr::getPath($checkpoint, 'id', '')
        );
        $status = (string)Arr::getPath($checkpoint, 'status', self::STATUS_READY);
        if (!Arr::hasKey(self::BEHAVIORS, $status)) {
            $status = self::STATUS_READY;
        }

        $plan->_data = Arr::make([
            'id' => $plan->id(),
            'version' => self::VERSION,
            'context' => Arr::make(Arr::getPath($checkpoint, 'context', []))->toArray(),
            'diagnostics' => Arr::make(Arr::getPath($checkpoint, 'diagnostics', []))->toArray(),
            'status' => $status,
            'approval_evidence' => Arr::make(Arr::getPath($checkpoint, 'approval_evidence', []))->toArray(),
            'checkpoints' => Arr::make(Arr::getPath($checkpoint, 'checkpoints', []))->toArray(),
            'outcomes' => Arr::make(Arr::getPath($checkpoint, 'outcomes', []))->toArray(),
            'created_at' => Arr::getPath($checkpoint, 'created_at', Date::now()->val()),
            'updated_at' => Arr::getPath($checkpoint, 'updated_at', Date::now()->val()),
            'approved_at' => Arr::getPath($checkpoint, 'approved_at'),
            'completed_at' => Arr::getPath($checkpoint, 'completed_at'),
            'failure' => Arr::getPath($checkpoint, 'failure'),
        ]);
        $plan->transition($status, false);

        return $plan;
    }

    public function id(): string
    {
        return (string)$this->_data->get('id');
    }

    public function status(): string
    {
        return (string)$this->_data->get('status');
    }

    public function behaviorState(): string
    {
        return self::BEHAVIORS[$this->status()];
    }

    public function context(): array
    {
        return Arr::make($this->_data->get('context', []))->toArray();
    }

    public function diagnostics(): array
    {
        return Arr::make($this->_data->get('diagnostics', []))->toArray();
    }

    public function applyDiagnostics(array $diagnostics): self
    {
        $normalized = (new Collection($diagnostics))->toArray();
        $this->_data->set('diagnostics', $normalized);
        $this->transition(
            Arr::isEmpty($normalized) ? self::STATUS_READY : self::STATUS_DRAFT
        );

        return $this;
    }

    public function approve(array $evidence = []): self
    {
        if ($this->status() !== self::STATUS_READY) {
            throw new \LogicException('Installation plan is not ready for approval.');
        }

        if (Arr::isNotEmpty($this->diagnostics())) {
            throw new \LogicException('An invalid installation plan cannot be approved.');
        }

        $this->_data->set('approval_evidence', Arr::make($evidence)->toArray());
        $this->_data->set('approved_at', Date::now()->val());
        $this->_data->set('failure', null);
        $this->transition(self::STATUS_APPROVED);

        return $this;
    }

    public function retry(): self
    {
        if ($this->status() !== self::STATUS_FAILED) {
            throw new \LogicException('Only a failed installation plan can be retried.');
        }

        $this->_data->set('failure', null);
        $this->_data->set('approval_evidence', []);
        $this->_data->set('approved_at', null);
        $this->transition(self::STATUS_READY);

        return $this;
    }

    public function start(bool $approvalRequired = false): self
    {
        $allowed = $approvalRequired
            ? [self::STATUS_APPROVED]
            : [self::STATUS_READY, self::STATUS_APPROVED];
        if (!Arr::has($allowed, $this->status(), true)) {
            throw new \LogicException(
                $approvalRequired
                    ? 'Installation execution requires host approval.'
                    : 'Installation plan is not ready for execution.'
            );
        }

        $this->transition(self::STATUS_EXECUTING);

        return $this;
    }

    public function checkpoint(string $stage, array $outcome): self
    {
        $checkpoints = Arr::make($this->_data->get('checkpoints', []));
        $outcomes = Arr::make($this->_data->get('outcomes', []));
        $record = Arr::make($outcome)
            ->merge(['stage' => $stage, 'recorded_at' => Date::now()->val()])
            ->toArray();

        $checkpoints->set($stage, $record);
        $outcomes->set($stage, $record);
        $this->_data->set('checkpoints', $checkpoints->toArray());
        $this->_data->set('outcomes', $outcomes->toArray());
        $this->touch();

        return $this;
    }

    public function completedStage(string $stage): bool
    {
        $checkpoint = Arr::getPath($this->_data->get('checkpoints', []), $stage, []);

        return Flag::make(Arr::getPath($checkpoint, 'ok', false))->parseBool();
    }

    public function complete(): self
    {
        $this->_data->set('completed_at', Date::now()->val());
        $this->transition(self::STATUS_COMPLETED);

        return $this;
    }

    public function fail(string $stage, array $failure): self
    {
        $this->_data->set('failure', Arr::make($failure)->merge(['stage' => $stage])->toArray());
        $this->transition(self::STATUS_FAILED);

        return $this;
    }

    private function transition(string $status, bool $touch = true): void
    {
        foreach (self::BEHAVIORS as $behavior) {
            $this->halt($behavior);
        }
        $this->halt(State::DRAFT);
        $this->_data->set('status', $status);
        if ($touch) {
            $this->touch();
        }
        $this->perform(self::BEHAVIORS[$status]);
    }

    private function touch(): void
    {
        $this->_data->set('updated_at', Date::now()->val());
    }
}
