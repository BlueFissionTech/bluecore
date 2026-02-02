<?php

namespace BlueFission\Utils;

class File
{
    public static function ensureFile($path, $contents = '', $overwrite = false)
    {
        $normalized = Path::normalize($path);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Path cannot be empty.');
        }

        $dir = dirname($normalized);
        if ($dir && $dir !== '.' && $dir !== DIRECTORY_SEPARATOR) {
            Path::ensureDir($dir);
        }

        if (file_exists($normalized) && !$overwrite) {
            return $normalized;
        }

        if (file_put_contents($normalized, $contents) === false) {
            throw new \RuntimeException("Unable to write file: {$normalized}");
        }

        return $normalized;
    }

    public static function writeAtomic($path, $contents)
    {
        $normalized = Path::normalize($path);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Path cannot be empty.');
        }

        $dir = dirname($normalized);
        if ($dir && $dir !== '.' && $dir !== DIRECTORY_SEPARATOR) {
            Path::ensureDir($dir);
        }

        $tmp = tempnam($dir, 'tmp');
        if ($tmp === false) {
            throw new \RuntimeException("Unable to create temp file in {$dir}");
        }

        if (file_put_contents($tmp, $contents) === false) {
            @unlink($tmp);
            throw new \RuntimeException("Unable to write temp file: {$tmp}");
        }

        if (!@rename($tmp, $normalized)) {
            @unlink($normalized);
            if (!@rename($tmp, $normalized)) {
                @unlink($tmp);
                throw new \RuntimeException("Unable to move temp file into place: {$normalized}");
            }
        }

        return $normalized;
    }
}
