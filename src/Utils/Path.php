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

    /**
     * Resolve a path through the local filesystem without treating lexical
     * normalization as a security boundary.
     *
     * Missing targets are resolved from their nearest existing ancestor. A
     * successful receipt can therefore report exists=false while still
     * returning a canonical path suitable for a later containment check.
     */
    public static function canonicalize($path, ?string $basePath = null): array
    {
        $resolution = self::resolveCanonicalPath($path, $basePath);

        return Arr::make([
            'resolved' => $resolution['resolved'],
            'inputPath' => $resolution['inputPath'],
            'normalizedPath' => $resolution['normalizedPath'],
            'canonicalPath' => $resolution['canonicalPath'],
            'exists' => $resolution['exists'],
            'reason' => $resolution['reason'],
        ])->toArray();
    }

    /**
     * Resolve a candidate and determine whether it is the root or one of its
     * descendants after filesystem canonicalization.
     *
     * This is a point-in-time check. Callers must still protect the eventual
     * file operation from time-of-check/time-of-use changes.
     */
    public static function containment($rootPath, $candidatePath): array
    {
        $root = self::resolveCanonicalPath($rootPath);
        if (!$root['resolved']) {
            return self::containmentReceipt(
                false,
                false,
                null,
                null,
                false,
                'root_' . $root['reason']
            );
        }

        if (!$root['exists']) {
            return self::containmentReceipt(false, false, $root['canonicalPath'], null, false, 'root_missing');
        }

        if (!is_dir($root['canonicalPath'])) {
            return self::containmentReceipt(false, false, $root['canonicalPath'], null, false, 'root_not_directory');
        }

        $candidate = self::resolveCanonicalPath($candidatePath, $root['canonicalPath']);
        if (!$candidate['resolved']) {
            return self::containmentReceipt(
                false,
                false,
                $root['canonicalPath'],
                $candidate['canonicalPath'],
                $candidate['exists'],
                $candidate['reason']
            );
        }

        $rootVolume = self::volumeKey($root['canonicalPath']);
        $candidateVolume = self::volumeKey($candidate['canonicalPath']);
        if (!self::volumesMatch($rootVolume, $candidateVolume)) {
            return self::containmentReceipt(
                true,
                false,
                $root['canonicalPath'],
                $candidate['canonicalPath'],
                $candidate['exists'],
                'different_volume'
            );
        }

        $rootComparison = self::comparisonPath($root['canonicalPath']);
        $candidateComparison = self::comparisonPath($candidate['canonicalPath']);
        $prefix = $rootComparison === '/'
            ? '/'
            : $rootComparison . '/';
        $contained = $candidateComparison === $rootComparison
            || Str::startsWith($candidateComparison, $prefix);

        return self::containmentReceipt(
            true,
            $contained,
            $root['canonicalPath'],
            $candidate['canonicalPath'],
            $candidate['exists'],
            $contained ? null : 'outside_root'
        );
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

    private static function resolveCanonicalPath($path, ?string $basePath = null): array
    {
        $inputPath = self::pathString($path);
        $reason = self::invalidPathReason($inputPath);
        if ($reason !== null) {
            return self::pathResolution(false, $inputPath, '', null, false, $reason);
        }

        $inputVolume = self::volumeKey($inputPath);
        if (
            (DIRECTORY_SEPARATOR === '\\' && $inputVolume === '/')
            || (DIRECTORY_SEPARATOR !== '\\' && $inputVolume !== null && $inputVolume !== '/')
        ) {
            return self::pathResolution(
                false,
                $inputPath,
                self::normalize($inputPath),
                null,
                false,
                'different_volume'
            );
        }

        $baseCanonical = null;
        if ($basePath !== null) {
            $baseInput = self::pathString($basePath);
            if (self::invalidPathReason($baseInput) !== null) {
                return self::pathResolution(false, $inputPath, '', null, false, 'base_invalid_path');
            }

            $baseCanonical = realpath(self::normalize($baseInput));
            if ($baseCanonical === false || !is_dir($baseCanonical)) {
                return self::pathResolution(false, $inputPath, '', null, false, 'base_unavailable');
            }

            if (self::isAbsolutePath($inputPath)) {
                if (!self::volumesMatch(self::volumeKey($baseCanonical), self::volumeKey($inputPath))) {
                    return self::pathResolution(false, $inputPath, self::normalize($inputPath), null, false, 'different_volume');
                }
            } else {
                $inputPath = $baseCanonical . DIRECTORY_SEPARATOR . $inputPath;
            }
        } elseif (!self::isAbsolutePath($inputPath)) {
            $workingDirectory = realpath((string)getcwd());
            if ($workingDirectory === false) {
                return self::pathResolution(false, $inputPath, '', null, false, 'resolution_unavailable');
            }

            $inputPath = $workingDirectory . DIRECTORY_SEPARATOR . $inputPath;
        }

        $normalizedPath = self::lexicalAbsolutePath($inputPath);
        if ($normalizedPath === null) {
            return self::pathResolution(false, $inputPath, self::normalize($inputPath), null, false, 'path_escape');
        }

        $cursor = $normalizedPath;
        $tail = [];
        while (!file_exists($cursor) && !is_link($cursor)) {
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                return self::pathResolution(false, $inputPath, $normalizedPath, null, false, 'resolution_unavailable');
            }

            $segment = basename($cursor);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return self::pathResolution(false, $inputPath, $normalizedPath, null, false, 'invalid_path');
            }

            array_unshift($tail, $segment);
            $cursor = $parent;
        }

        $canonicalAncestor = realpath($cursor);
        if ($canonicalAncestor === false) {
            return self::pathResolution(false, $inputPath, $normalizedPath, null, false, 'resolution_unavailable');
        }

        if ($tail !== [] && !is_dir($canonicalAncestor)) {
            return self::pathResolution(false, $inputPath, $normalizedPath, null, false, 'ancestor_not_directory');
        }

        $canonicalPath = self::normalize($canonicalAncestor);
        if ($tail !== []) {
            $canonicalPath = self::normalize(
                rtrim($canonicalPath, '/\\')
                . DIRECTORY_SEPARATOR
                . implode(DIRECTORY_SEPARATOR, $tail)
            );
        }

        return self::pathResolution(
            true,
            self::pathString($path),
            $normalizedPath,
            $canonicalPath,
            file_exists($normalizedPath),
            null
        );
    }

    private static function pathString($path): ?string
    {
        if (is_string($path)) {
            return $path;
        }

        if (is_object($path) && method_exists($path, '__toString')) {
            try {
                return (string)$path;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function invalidPathReason(?string $path): ?string
    {
        if ($path === null || $path === '' || strpos($path, "\0") !== false) {
            return 'invalid_path';
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//', $path) === 1) {
            return 'invalid_path';
        }

        if (preg_match('/[*?\[\]]/', $path) === 1) {
            return 'invalid_path';
        }

        if (preg_match('#(?:^|[\\\\/])\.\.(?:[\\\\/]|$)#', $path) === 1) {
            return 'path_escape';
        }

        if (preg_match('/^[A-Za-z]:[^\\\\\/]/', $path) === 1) {
            return 'invalid_path';
        }

        // Win32 aliases (trailing dots/spaces, streams, devices) can change the
        // meaning of an appended pending segment during a later file operation.
        if (DIRECTORY_SEPARATOR === '\\') {
            $segments = preg_split('#[\\\\/]+#', $path);
            foreach ($segments as $index => $segment) {
                if ($segment === '' || $segment === '.' || ($index === 0 && preg_match('/^[A-Za-z]:$/', $segment))) {
                    continue;
                }
                if (preg_match('/[<>:"|\x00-\x1f]/', $segment)
                    || preg_match('/[. ]$/', $segment)
                    || preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $segment)) {
                    return 'invalid_path';
                }
            }
            if (preg_match('/^[A-Za-z]:$/', $path)) {
                return 'invalid_path';
            }
        }

        return null;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            || preg_match('#^[\\\\/]{2}[^\\\\/]+[\\\\/]+[^\\\\/]+#', $path) === 1
            || Str::startsWith($path, '/')
            || Str::startsWith($path, '\\');
    }

    private static function lexicalAbsolutePath(string $path): ?string
    {
        $portable = str_replace('\\', '/', $path);
        $prefix = null;
        $rest = '';

        if (preg_match('#^//+([^/]+)/+([^/]+)(?:/(.*))?$#', $portable, $matches) === 1) {
            $prefix = '//' . $matches[1] . '/' . $matches[2];
            $rest = $matches[3] ?? '';
        } elseif (preg_match('#^([A-Za-z]):/(.*)$#', $portable, $matches) === 1) {
            $prefix = strtoupper($matches[1]) . ':';
            $rest = $matches[2];
        } elseif (Str::startsWith($portable, '/')) {
            $prefix = '/';
            $rest = ltrim($portable, '/');
        }

        if ($prefix === null) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $rest) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }

                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        if ($prefix === '/') {
            $resolved = '/' . implode('/', $segments);
        } elseif (preg_match('/^[A-Z]:$/', $prefix) === 1) {
            $resolved = $prefix . '/' . implode('/', $segments);
        } else {
            $resolved = $prefix . ($segments === [] ? '' : '/' . implode('/', $segments));
        }

        return self::normalize($resolved);
    }

    private static function volumeKey(string $path): ?string
    {
        $portable = str_replace('\\', '/', $path);

        if (preg_match('#^([A-Za-z]):/#', $portable, $matches) === 1) {
            return strtoupper($matches[1]) . ':';
        }

        if (preg_match('#^//+([^/]+)/+([^/]+)#', $portable, $matches) === 1) {
            return '//' . strtolower($matches[1]) . '/' . strtolower($matches[2]);
        }

        return Str::startsWith($portable, '/') ? '/' : null;
    }

    private static function volumesMatch(?string $left, ?string $right): bool
    {
        return $left !== null
            && $right !== null
            && strtolower($left) === strtolower($right);
    }

    private static function comparisonPath(string $path): string
    {
        $portable = str_replace('\\', '/', $path);
        $portable = rtrim($portable, '/');
        if ($portable === '') {
            $portable = '/';
        }

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($portable) : $portable;
    }

    private static function pathResolution(
        bool $resolved,
        ?string $inputPath,
        string $normalizedPath,
        ?string $canonicalPath,
        bool $exists,
        ?string $reason
    ): array {
        return [
            'resolved' => $resolved,
            'inputPath' => $inputPath,
            'normalizedPath' => $normalizedPath,
            'canonicalPath' => $canonicalPath,
            'exists' => $exists,
            'reason' => $reason,
        ];
    }

    private static function containmentReceipt(
        bool $resolved,
        bool $contained,
        ?string $rootPath,
        ?string $candidatePath,
        bool $candidateExists,
        ?string $reason
    ): array {
        return Arr::make([
            'resolved' => $resolved,
            'contained' => $contained,
            'rootPath' => $rootPath,
            'candidatePath' => $candidatePath,
            'candidateExists' => $candidateExists,
            'reason' => $reason,
        ])->toArray();
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
