<?php

namespace BlueFission\Utils;

use BlueFission\Data\File as BaseFile;
use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Val;

class File extends BaseFile
{
    public static function ensureFile($path, $contents = '', $overwrite = false)
    {
        $normalized = Path::normalize($path);
        if (Val::isEmpty($normalized)) {
            throw new \InvalidArgumentException('Path cannot be empty.');
        }

        $dir = Path::parentPath($normalized);
        if (Val::isNotEmpty($dir) && $dir !== '.' && $dir !== DIRECTORY_SEPARATOR) {
            Path::ensureDir($dir);
        }

        if ((new static())->exists($normalized) && Flag::isFalse($overwrite)) {
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
        if (Val::isEmpty($normalized)) {
            throw new \InvalidArgumentException('Path cannot be empty.');
        }

        $dir = Path::parentPath($normalized);
        if (Val::isNotEmpty($dir) && $dir !== '.' && $dir !== DIRECTORY_SEPARATOR) {
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

    public static function readContents($path): string
    {
        $normalized = Path::normalize($path);
        if (Val::isEmpty($normalized)) {
            throw new \InvalidArgumentException('Path cannot be empty.');
        }

        if (!(new static())->isReachable($normalized)) {
            throw new \RuntimeException("Unable to read file: {$normalized}");
        }

        $contents = file_get_contents($normalized);
        if ($contents === false) {
            throw new \RuntimeException("Unable to read file: {$normalized}");
        }

        return $contents;
    }

    public static function readiness($path, bool $includeHash = false): array
    {
        $normalized = Path::normalize($path);
        $exists = Val::isNotEmpty($normalized) && is_file($normalized);
        $readable = $exists && is_readable($normalized);
        $writable = $exists
            ? is_writable($normalized)
            : self::parentIsWritable($normalized);

        $result = [
            'normalizedPath' => $normalized,
            'expectedType' => 'file',
            'exists' => Flag::parseBool($exists),
            'readable' => Flag::parseBool($readable),
            'writable' => Flag::parseBool($writable),
            'reason' => self::readinessReason($normalized, $exists, $readable, $writable),
            'hash' => null,
        ];

        if (Flag::parseBool($includeHash) && Flag::parseBool($readable)) {
            $result['hash'] = hash_file('sha256', $normalized) ?: null;
        }

        return Arr::make($result)->toArray();
    }

    private static function parentIsWritable(string $path): bool
    {
        if (Val::isEmpty($path)) {
            return false;
        }

        $dir = Path::parentPath($path);

        return Val::isNotEmpty($dir) && is_dir($dir) && is_writable($dir);
    }

    private static function readinessReason(string $path, bool $exists, bool $readable, bool $writable): ?string
    {
        if (Val::isEmpty($path)) {
            return 'invalid_path';
        }

        if (file_exists($path) && !$exists) {
            return 'not_file';
        }

        if (!$exists) {
            return self::parentIsWritable($path) ? 'missing' : 'parent_unavailable';
        }

        if (!$readable) {
            return 'unreadable';
        }

        if (!$writable) {
            return 'unwritable';
        }

        return null;
    }
}
