<?php

namespace BlueFission\Utils;

use BlueFission\Arr;
use BlueFission\Data\Directory;
use BlueFission\Data\FileSystem;
use BlueFission\Flag;
use BlueFission\Func;
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

    public static function resolveProjectPath(
        $pathInProject,
        ?string $applicationRoot = null,
        ?string $legacyProjectRoot = null,
        ?Func $fallbackResolver = null
    ): string {
        $applicationRoot ??= defined('APP_ROOT') ? (string)constant('APP_ROOT') : (string)getcwd();
        $legacyProjectRoot ??= defined('PROJECT_ROOT')
            ? (string)constant('PROJECT_ROOT')
            : $applicationRoot;

        $relativePath = self::normalize((string)$pathInProject);
        $candidate = self::normalize(
            $applicationRoot . DIRECTORY_SEPARATOR . $relativePath
        );
        $fallback = self::normalize(
            $legacyProjectRoot . DIRECTORY_SEPARATOR . $relativePath
        );
        $hasWildcard = Str::matchPattern($relativePath, '/[*?\[]/');

        if (!$hasWildcard && (
            FileSystem::fileExists($candidate)
            || FileSystem::directoryExists($candidate)
        )) {
            return $candidate;
        }

        if ($hasWildcard) {
            $matches = glob($candidate);
            if (!Flag::isFalse($matches) && Arr::isNotEmpty($matches)) {
                return $candidate;
            }
        }

        if (Val::isNotNull($fallbackResolver)) {
            $resolved = self::normalize((string)$fallbackResolver->call($relativePath));
            if (Val::isNotEmpty($resolved)) {
                return $resolved;
            }
        }

        return $fallback;
    }

    public static function readiness($path): array
    {
        $normalized = self::normalize($path);
        $exists = Val::isNotEmpty($normalized) && is_dir($normalized);
        $readable = $exists && is_readable($normalized);
        $writable = $exists && is_writable($normalized);

        return Arr::make([
            'normalizedPath' => $normalized,
            'expectedType' => 'directory',
            'exists' => Flag::parseBool($exists),
            'readable' => Flag::parseBool($readable),
            'writable' => Flag::parseBool($writable),
            'reason' => self::readinessReason($normalized, $exists, $readable, $writable),
            'hash' => null,
        ])->toArray();
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

    private static function readinessReason(string $path, bool $exists, bool $readable, bool $writable): ?string
    {
        if (Val::isEmpty($path)) {
            return 'invalid_path';
        }

        if (file_exists($path) && !$exists) {
            return 'not_directory';
        }

        if (!$exists) {
            return 'missing';
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
