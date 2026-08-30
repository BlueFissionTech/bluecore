<?php

namespace BlueFission\BlueCore\Installation;

use BlueFission\Arr;
use BlueFission\BlueCore\Contracts\IInstallationCheckpointStore;
use BlueFission\Data\FileSystem;
use BlueFission\Net\HTTP;
use BlueFission\Str;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use BlueFission\Val;

class JsonInstallationCheckpointStore implements IInstallationCheckpointStore
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = Path::ensureDir($directory);
    }

    public function load(string $planId): ?array
    {
        $path = $this->path($planId);
        if (!FileSystem::fileExists($path)) {
            return null;
        }

        $decoded = HTTP::jsonDecode(File::readContents($path), true, null);
        if (!Arr::is($decoded)) {
            throw new \RuntimeException("Invalid installation checkpoint for {$planId}.");
        }

        return Arr::make($decoded)->toArray();
    }

    public function save(string $planId, array $checkpoint): void
    {
        $encoded = HTTP::jsonEncode(Arr::make($checkpoint)->toArray());
        if (!Str::is($encoded)) {
            throw new \RuntimeException("Unable to encode installation checkpoint for {$planId}.");
        }

        File::writeAtomic($this->path($planId), $encoded);
    }

    public function delete(string $planId): void
    {
        $path = $this->path($planId);
        if (FileSystem::fileExists($path) && !File::deletePath($path)) {
            throw new \RuntimeException("Unable to delete installation checkpoint for {$planId}.");
        }
    }

    private function path(string $planId): string
    {
        $id = Str::make($planId)->trim()->val();
        if (Val::isEmpty($id) || !Str::matchPattern($id, '/^[A-Za-z0-9._-]+$/')) {
            throw new \InvalidArgumentException('Installation plan id contains unsupported characters.');
        }

        return Path::normalize($this->directory.DIRECTORY_SEPARATOR.$id.'.json');
    }
}
