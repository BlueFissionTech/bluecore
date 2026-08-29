<?php

namespace BlueFission\Tests\BlueCore;

use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\System\Process;
use BlueFission\Utils\Path;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ThemeDirectoryResolutionTest extends TestCase
{
    #[DataProvider('resolverModes')]
    public function testThemeResolvesReadableTemplatesAcrossCompatibleHostHelpers(
        string $mode,
        string $expectedRoot
    ): void {
        $root = Path::normalize(
            sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'bluecore-theme-resolution-'
            . Str::rand('', 10)
        );

        try {
            $result = $this->runFixture($root, $mode);
            $expectedLocation = Path::normalize(
                $root
                . DIRECTORY_SEPARATOR . $expectedRoot
                . DIRECTORY_SEPARATOR . 'addons'
                . DIRECTORY_SEPARATOR . 'vendor'
                . DIRECTORY_SEPARATOR . 'resource'
                . DIRECTORY_SEPARATOR . 'markup'
                . DIRECTORY_SEPARATOR . 'location'
                . DIRECTORY_SEPARATOR
            );

            $this->assertSame($expectedLocation, $result['location']);
            $this->assertSame('login template', $result['contents']);
            $this->assertSame(
                Path::normalize($expectedLocation . 'login.vibe'),
                $result['template']
            );
        } finally {
            $this->removeDirectory($root);
        }
    }

    public static function resolverModes(): array
    {
        return [
            'directory-aware application helper' => ['directory-aware', 'application'],
            'file-only application helper' => ['file-only', 'application'],
            'legacy project fallback' => ['project-fallback', 'project'],
        ];
    }

    private function runFixture(string $root, string $mode): array
    {
        $projectRoot = Path::normalize(dirname(__DIR__, 2));
        $fixture = Path::normalize(
            $projectRoot
            . DIRECTORY_SEPARATOR . 'tests'
            . DIRECTORY_SEPARATOR . 'Fixtures'
            . DIRECTORY_SEPARATOR . 'theme-directory-resolution.php'
        );
        $command = Str::make(escapeshellarg(PHP_BINARY))
            ->append(' ')
            ->append(escapeshellarg($fixture))
            ->append(' ')
            ->append(escapeshellarg($root))
            ->append(' ')
            ->append(escapeshellarg($mode))
            ->val();
        $process = new Process($command, $projectRoot, [], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ]);

        $process->start();
        while ($process->status() === true) {
            usleep(10000);
        }

        $output = $process->output();
        $process->close();
        $this->assertJson($output);

        return Arr::toArray(json_decode($output, true, flags: JSON_THROW_ON_ERROR), true);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
