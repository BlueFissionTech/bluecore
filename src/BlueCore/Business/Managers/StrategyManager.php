<?php

namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Services\Service;
use BlueFission\Automata\DataGroup;

class StrategyManager extends Service
{
    private $dataGroups;

    public function __construct()
    {
        $this->dataGroups = [];
    }

    public function registerStrategy(string $dataType, Strategy $strategy)
    {
        if (!isset($this->dataGroups[$dataType])) {
            $this->dataGroups[$dataType] = new DataGroup();
        }

        $this->dataGroups[$dataType]->add($strategy);
    }

    public function getDataGroup(string $dataType): ?DataGroup
    {
        return $this->dataGroups[$dataType] ?? null;
    }

    public function getDataGroups(): array
    {
        return $this->dataGroups;
    }
}
