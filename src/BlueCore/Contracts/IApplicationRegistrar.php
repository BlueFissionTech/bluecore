<?php

namespace BlueFission\BlueCore\Contracts;

use BlueFission\BlueCore\Registration\RegistrationPlan;
use BlueFission\Services\Application;

interface IApplicationRegistrar
{
    public function register(Application $app, RegistrationPlan $plan): void;
}
