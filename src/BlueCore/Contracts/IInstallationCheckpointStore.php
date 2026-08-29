<?php

namespace BlueFission\BlueCore\Contracts;

interface IInstallationCheckpointStore
{
    public function load(string $planId): ?array;

    public function save(string $planId, array $checkpoint): void;

    public function delete(string $planId): void;
}
