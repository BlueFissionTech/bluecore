<?php

namespace BlueFission\Utils;

use BlueFission\Data\Directory as DevElationDirectory;

class Path extends DevElationDirectory
{
    public static function normalize($path)
    {
        if ($path === null) {
            return '';
        }

        $path = (string)$path;
        if ($path === '') {
            return '';
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        $isUnc = (DIRECTORY_SEPARATOR === '\\' && strpos($path, '\\\\') === 0);
        $separator = preg_quote(DIRECTORY_SEPARATOR, '#');
        $path = preg_replace('#' . $separator . '+#', DIRECTORY_SEPARATOR, $path);

        if ($isUnc) {
            $path = '\\\\' . ltrim($path, '\\');
        }

        return $path;
    }

    public static function ensureDir($path, $mode = 0775, $recursive = true)
    {
        $normalized = self::normalize($path);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Path cannot be empty.');
        }

        if (is_dir($normalized)) {
            return $normalized;
        }

        if (file_exists($normalized)) {
            throw new \RuntimeException("Path exists and is not a directory: {$normalized}");
        }

        if (!@mkdir($normalized, $mode, $recursive) && !is_dir($normalized)) {
            throw new \RuntimeException("Unable to create directory: {$normalized}");
        }

        return $normalized;
    }
}
