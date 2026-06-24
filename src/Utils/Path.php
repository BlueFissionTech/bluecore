<?php

namespace BlueFission\Utils;

use BlueFission\Data\Directory;
use BlueFission\Str;
use BlueFission\Val;

class Path extends Directory
{
    public static function normalize($path)
    {
        if (Val::isNull($path)) {
            return '';
        }

        $path = (string)$path;
        if (Val::isEmpty($path)) {
            return '';
        }

        $path = Str::replace($path, '/', DIRECTORY_SEPARATOR);
        $path = Str::replace($path, '\\', DIRECTORY_SEPARATOR);

        $isUnc = (DIRECTORY_SEPARATOR === '\\' && Str::startsWith($path, '\\\\'));
        $separator = preg_quote(DIRECTORY_SEPARATOR, '#');
        $path = Str::replacePattern($path, '#' . $separator . '+#', DIRECTORY_SEPARATOR);

        if ($isUnc) {
            $path = '\\\\' . Str::trim($path, '\\');
        }

        return $path;
    }

    public static function parentPath($path)
    {
        return dirname(self::normalize($path));
    }

    public static function ensureDir($path, $mode = 0775, $recursive = true)
    {
        $normalized = self::normalize($path);
        if (Val::isEmpty($normalized)) {
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
