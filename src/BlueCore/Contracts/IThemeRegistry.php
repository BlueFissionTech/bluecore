<?php

namespace BlueFission\BlueCore\Contracts;

interface IThemeRegistry
{
    public function registerTheme(string $name, ?string $location = null): mixed;
    public function theme(string $name): mixed;
}
