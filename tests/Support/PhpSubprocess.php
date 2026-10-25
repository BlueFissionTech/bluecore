<?php

namespace BlueFission\Tests\Support;

use InvalidArgumentException;

/** Select a test-only child profile without inheriting the host environment. */
final class PhpSubprocess
{
    public static function command(): string
    {
        $command = escapeshellarg(PHP_BINARY);
        $profile = getenv('BLUECORE_TEST_PHP_INI');
        if ($profile === false || $profile === '') {
            return $command;
        }
        if (!is_file($profile)) {
            throw new InvalidArgumentException('The selected PHP test profile is not a file.');
        }
        return $command . ' -c ' . escapeshellarg($profile);
    }
}
