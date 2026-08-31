<?php

namespace BlueFission\BlueCore\Registration;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;

final class RegistrationEntry extends Obj
{
    public function __construct(string $section, string $name, mixed $definition)
    {
        parent::__construct();

        $this->_data = Arr::make([
            'section' => Str::make($section)->trim()->val(),
            'name' => Str::make($name)->trim()->val(),
            'definition' => $definition,
        ]);
    }

    public function section(): string
    {
        return $this->_data['section'];
    }

    public function name(): string
    {
        return $this->_data['name'];
    }

    public function definition(): mixed
    {
        return $this->_data['definition'];
    }
}
