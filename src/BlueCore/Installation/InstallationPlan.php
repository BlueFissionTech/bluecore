<?php

namespace BlueFission\BlueCore\Installation;

use BlueFission\Arr;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\Collections\Collection;
use BlueFission\Date;
use BlueFission\Flag;
use BlueFission\Net\HTTP;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

class InstallationPlan extends Obj
{
    public const VERSION = 1;
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW_READY = 'review_ready';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    private const BEHAVIORS = [
        self::STATUS_DRAFT => 'IsInstallationDraft',
        self::STATUS_REVIEW_READY => 'IsInstallationReviewReady',
        self::STATUS_APPROVED => 'IsInstallationApproved',
        self::STATUS_EXECUTING => 'IsInstallationExecuting',
        self::STATUS_COMPLETED => 'IsInstallationCompleted',
        self::STATUS_FAILED => 'IsInstallationFailed',
    ];

    public function __construct(array $packet = [])
    {
        parent::__construct();

        $values = Arr::make(Arr::getPath($packet, 'values', []));
        $projectName = Str::make((string)Arr::getPath(
            $packet,
            'project_name',
            $values->get('project_name', '')
        ))->trim()->val();
        if (Val::isNotEmpty($projectName)) {
            $values->set('project_name', $projectName);
        }

        $diagnostics = new Collection();
        $version = (int)Arr::getPath($packet, 'version', self::VERSION);
        if ($version !== self::VERSION) {
            $diagnostics->add([
                'field' => 'version',
                'reason' => 'unsupported_version',
                'message' => 'The installation plan version is not supported.',
            ]);
        }
        if (Val::isEmpty($projectName)) {
            $diagnostics->add([
                'field' => 'project_name',
                'reason' => 'required',
                'message' => 'Project name is required.',
            ]);
        }

        $id = Str::make((string)Arr::getPath($packet, 'id', ''))->trim()->val();
        if (Val::isEmpty($id)) {
            $slug = Str::make(Val::isEmpty($projectName) ? 'installation' : $projectName)->slugify();
            $fingerprint = Str::make((string)HTTP::jsonEncode($packet))->encrypt('sha1')->val();
            $id = Str::make((string)$slug)->append('-')->append(Str::sub($fingerprint, 0, 12))->val();
        }

        $status = Arr::isEmpty($diagnostics->toArray())
            ? self::STATUS_REVIEW_READY
            : self::STATUS_DRAFT;
        $now = Date::now()->val();

        $this->_data = Arr::make([
            'id' => $id,
            'version' => self::VERSION,
            'project_name' => $projectName,
            'values' => $values->toArray(),
            'defaults' => Arr::make(Arr::getPath($packet, 'defaults', []))->toArray(),
            'skips' => Arr::make(Arr::getPath($packet, 'skips', []))->values()->unique()->toArray(),
            'permissions' => Arr::make(Arr::getPath($packet, 'permissions', []))->values()->unique()->toArray(),
            'granted_permissions' => Arr::make(Arr::getPath($packet, 'granted_permissions', []))->values()->unique()->toArray(),
            'diagnostics' => $diagnostics->toArray(),
            'status' => $status,
            'checkpoints' => Arr::make(Arr::getPath($packet, 'checkpoints', []))->toArray(),
            'outcomes' => Arr::make(Arr::getPath($packet, 'outcomes', []))->toArray(),
            'created_at' => Arr::getPath($packet, 'created_at', $now),
            'updated_at' => $now,
            'approved_at' => Arr::getPath($packet, 'approved_at'),
            'completed_at' => Arr::getPath($packet, 'completed_at'),
            'failure' => Arr::getPath($packet, 'failure'),
        ]);

        $this->transition($status);
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
        $plan = new self($checkpoint);
        $status = (string)Arr::getPath($checkpoint, 'status', '');
        if (Arr::hasKey(self::BEHAVIORS, $status)) {
            $plan->transition($status);
        }

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

    public function diagnostics(): array
    {
        return Arr::make($this->_data->get('diagnostics', []))->toArray();
    }

    public function values(): array
    {
        return Arr::make($this->_data->get('values', []))
            ->merge($this->_data->get('defaults', []))
            ->merge($this->_data->get('values', []))
            ->toArray();
    }

    public function skipped(string $stage): bool
    {
        return Arr::has($this->_data->get('skips', []), $stage, true);
    }

    public function requestPermissions(array $permissions): self
    {
        $requested = Arr::make($this->_data->get('permissions', []))
            ->merge($permissions)
            ->values()
            ->unique()
            ->toArray();
        $this->_data->set('permissions', $requested);
        $this->touch();

        return $this;
    }

    public function approve(array $grantedPermissions): self
    {
        if (!Arr::has(
            [self::STATUS_REVIEW_READY, self::STATUS_FAILED],
            $this->status(),
            true
        )) {
            throw new \LogicException('Installation plan is not ready for approval.');
        }

        if (Arr::isNotEmpty($this->diagnostics())) {
            throw new \LogicException('An invalid installation plan cannot be approved.');
        }

        $granted = Arr::make($grantedPermissions)->values()->unique()->toArray();
        $missing = Arr::make($this->_data->get('permissions', []))->diff($granted)->toArray();
        if (Arr::isNotEmpty($missing)) {
            throw new \LogicException(
                'Installation permissions require review: '.Arr::make($missing)->join(', ')->val()
            );
        }

        $this->_data->set('granted_permissions', $granted);
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

        return $this->approve($this->_data->get('granted_permissions', []));
    }

    public function start(): self
    {
        if ($this->status() !== self::STATUS_APPROVED) {
            throw new \LogicException('Installation execution requires explicit approval.');
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

        return Flag::make(Arr::getPath($checkpoint, 'ok', false))->parseBool()
            && !Flag::make(Arr::getPath($checkpoint, 'skipped', false))->parseBool();
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

    private function transition(string $status): void
    {
        foreach (self::BEHAVIORS as $behavior) {
            $this->halt($behavior);
        }
        $this->halt(State::DRAFT);
        $this->_data->set('status', $status);
        $this->touch();
        $this->perform(self::BEHAVIORS[$status]);
    }

    private function touch(): void
    {
        $this->_data->set('updated_at', Date::now()->val());
    }
}
