<?php

namespace BlueFission\BlueCore\Integration\Presence;

use BlueFission\Arr;
use RuntimeException;

class PresenceBridgeUnavailable extends RuntimeException
{
    private array $details;

    public function __construct(array $missingContracts)
    {
        $this->details = Arr::make([
            'reason' => 'presence_contracts_unavailable',
            'missing_contracts' => Arr::toArray($missingContracts, true),
        ])->toArray(true);

        parent::__construct('Presence bridge contracts are unavailable.');
    }

    public function reason(): string
    {
        return (string)$this->details['reason'];
    }

    public function details(): array
    {
        return $this->details;
    }
}
