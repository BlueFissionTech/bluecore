<?php

declare(strict_types=1);

use BlueFission\Arr;
use BlueFission\Flag;
use BlueFission\Net\HTTP;
use BlueFission\Str;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

restore_error_handler();
restore_exception_handler();

$root = Path::normalize(dirname(__DIR__, 2));
$workspace = Path::normalize(
    sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bluecore-installer-' . Str::rand('', 10)
);
$composer = getenv('COMPOSER_BINARY') ?: 'composer';

try {
    Path::ensureDir($workspace);
    $plugin = createPluginPackage($workspace, $root);
    $fixture = createFixturePackage($workspace);

    verifyTransport($workspace, $plugin, $fixture, $composer, 'dist');
    verifyTransport($workspace, $plugin, $fixture, $composer, 'source');
    verifyFailureRollback($workspace, $plugin, $fixture, $composer);

    fwrite(STDOUT, "Project installer source/dist parity passed.\n");
} finally {
    removeTree($workspace);
}

function createPluginPackage(string $workspace, string $root): string
{
    $plugin = Path::normalize($workspace . DIRECTORY_SEPARATOR . 'bluecore-plugin');
    $installerRoot = 'src' . DIRECTORY_SEPARATOR . 'BlueCore' . DIRECTORY_SEPARATOR . 'Installers';
    Path::ensureDir($plugin . DIRECTORY_SEPARATOR . $installerRoot);

    foreach (Arr::make(['AddOnInstaller.php', 'ThemeInstaller.php', 'ProjectInstaller.php', 'PluginInstaller.php']) as $file) {
        File::ensureFile(
            $plugin . DIRECTORY_SEPARATOR . $installerRoot . DIRECTORY_SEPARATOR . $file,
            File::readContents($root . DIRECTORY_SEPARATOR . $installerRoot . DIRECTORY_SEPARATOR . $file),
            true
        );
    }
    File::ensureFile(
        $plugin . DIRECTORY_SEPARATOR . 'composer.json',
        HTTP::jsonEncode([
            'name' => 'bluefission/bluecore',
            'version' => '0.0.0',
            'type' => 'composer-plugin',
            'require' => [
                'php' => '>=8.2',
                'composer-plugin-api' => '^2.0',
            ],
            'autoload' => [
                'psr-4' => ['BlueFission\\' => 'src/'],
            ],
            'extra' => [
                'class' => 'BlueFission\\BlueCore\\Installers\\PluginInstaller',
            ],
        ]),
        true
    );

    return $plugin;
}

/**
 * @return array{source: string, reference: string, dist: array<string, string>}
 */
function createFixturePackage(string $workspace): array
{
    $source = Path::normalize($workspace . DIRECTORY_SEPARATOR . 'project-package');
    Path::ensureDir($source);

    writeFixtureVersion($source, '1.0.0', [
        'common' . DIRECTORY_SEPARATOR . 'config.php' => 'version-one',
        'package.json' => '{"version":"1.0.0"}',
    ]);
    runProcess(['git', 'init'], $source);
    runProcess(['git', 'config', 'user.email', 'installer-fixture@bluefission.com'], $source);
    runProcess(['git', 'config', 'user.name', 'BlueCore Installer Fixture'], $source);
    runProcess(['git', 'add', '.'], $source);
    runProcess(['git', 'commit', '-m', 'fixture version 1.0.0'], $source);
    runProcess(['git', 'tag', '1.0.0'], $source);

    $dist10 = buildArchive($workspace, $source, '1.0.0');

    writeFixtureVersion($source, '1.1.0', [
        'common' . DIRECTORY_SEPARATOR . 'config.php' => 'version-two',
        'common' . DIRECTORY_SEPARATOR . 'new.php' => 'new-override',
        'package.json' => '{"version":"1.1.0"}',
    ]);
    runProcess(['git', 'add', '.'], $source);
    runProcess(['git', 'commit', '-m', 'fixture version 1.1.0'], $source);
    runProcess(['git', 'tag', '1.1.0'], $source);
    $reference = Str::trim(runProcess(['git', 'rev-parse', 'HEAD'], $source)['output']);
    $dist11 = buildArchive($workspace, $source, '1.1.0');

    return Arr::make([
        'source' => $source,
        'reference' => $reference,
        'dist' => [
            '1.0.0' => $dist10,
            '1.1.0' => $dist11,
        ],
    ])->toArray();
}

function writeFixtureVersion(string $source, string $version, array $overrides): void
{
    File::ensureFile(
        $source . DIRECTORY_SEPARATOR . 'composer.json',
        HTTP::jsonEncode([
            'name' => 'bluefission/project-installer-fixture',
            'version' => $version,
            'type' => 'opus-project',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        true
    );
    File::ensureFile($source . DIRECTORY_SEPARATOR . 'marker.txt', $version, true);

    foreach (Arr::make($overrides) as $path => $contents) {
        File::ensureFile($source . DIRECTORY_SEPARATOR . $path, $contents, true);
    }
}

function buildArchive(string $workspace, string $source, string $version): string
{
    $archivePath = Path::normalize($workspace . DIRECTORY_SEPARATOR . "fixture-{$version}.zip");
    $archive = new ZipArchive();
    if ($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Unable to create fixture archive {$archivePath}.");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        $path = Path::normalize($item->getPathname());
        if (Str::has($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $relative = Str::sub($path, Str::len($source) + 1);
        $archive->addFile($path, Str::replace($relative, DIRECTORY_SEPARATOR, '/'));
    }
    $archive->close();

    return $archivePath;
}

function verifyTransport(string $workspace, string $plugin, array $fixture, string $composer, string $transport): void
{
    $consumer = Path::normalize($workspace . DIRECTORY_SEPARATOR . "consumer-{$transport}");
    Path::ensureDir($consumer);
    writeConsumerManifest($consumer, $plugin, $fixture, '1.0.0');

    $install = runProcess(
        composerCommand($composer, ['install', '--no-interaction', '--no-progress', "--prefer-{$transport}"]),
        $consumer,
        true
    );
    assertProcessSucceeded($install, "{$transport} install");
    assertCleanComposerOutput($install, "{$transport} install");
    assertFile($consumer . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'marker.txt', '1.0.0');
    assertFile($consumer . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'config.php', 'version-one');
    assertFile($consumer . DIRECTORY_SEPARATOR . 'package.json', '{"version":"1.0.0"}');

    writeConsumerManifest($consumer, $plugin, $fixture, '1.1.0');
    $update = runProcess(
        composerCommand($composer, ['update', 'bluefission/project-installer-fixture', '--with-dependencies', '--no-interaction', '--no-progress', "--prefer-{$transport}"]),
        $consumer,
        true
    );
    assertProcessSucceeded($update, "{$transport} update");
    assertCleanComposerOutput($update, "{$transport} update");
    assertFile($consumer . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'marker.txt', '1.1.0');
    assertFile($consumer . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'config.php', 'version-one');
    assertFile($consumer . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'new.php', 'new-override');

    $remove = runProcess(
        composerCommand($composer, ['remove', 'bluefission/project-installer-fixture', '--no-interaction', '--no-progress']),
        $consumer,
        true
    );
    assertProcessSucceeded($remove, "{$transport} uninstall");
    assertCleanComposerOutput($remove, "{$transport} uninstall");
    if ((new File())->exists($consumer . DIRECTORY_SEPARATOR . 'core')) {
        throw new RuntimeException("{$transport} uninstall left the project install path behind.");
    }
    assertFile($consumer . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'config.php', 'version-one');
}

function verifyFailureRollback(string $workspace, string $plugin, array $fixture, string $composer): void
{
    $consumer = Path::normalize($workspace . DIRECTORY_SEPARATOR . 'consumer-failure');
    Path::ensureDir($consumer);
    File::ensureFile($consumer . DIRECTORY_SEPARATOR . 'common', 'blocking-file', true);
    writeConsumerManifest($consumer, $plugin, $fixture, '1.0.0');

    $install = runProcess(
        composerCommand($composer, ['install', '--no-interaction', '--no-progress', '--prefer-dist']),
        $consumer,
        true
    );
    if ($install['exitCode'] === 0) {
        throw new RuntimeException('A failed override copy returned a successful Composer exit code.');
    }
    if ((new File())->exists($consumer . DIRECTORY_SEPARATOR . 'core')) {
        throw new RuntimeException('A failed install left a partial project path behind.');
    }
    assertFile($consumer . DIRECTORY_SEPARATOR . 'common', 'blocking-file');
}

function writeConsumerManifest(string $consumer, string $plugin, array $fixture, string $version): void
{
    $packages = Arr::make(['1.0.0', '1.1.0'])
        ->map(fn (string $candidate): array => [
            'name' => 'bluefission/project-installer-fixture',
            'version' => $candidate,
            'type' => 'opus-project',
            'dist' => [
                'type' => 'zip',
                'url' => fileUrl($fixture['dist'][$candidate]),
            ],
            'source' => [
                'type' => 'git',
                'url' => Str::replace($fixture['source'], DIRECTORY_SEPARATOR, '/'),
                'reference' => $candidate,
            ],
        ])
        ->toArray();
    $repositories = Arr::make([
        [
            'type' => 'path',
            'url' => Str::replace($plugin, DIRECTORY_SEPARATOR, '/'),
            'options' => ['symlink' => false],
            'canonical' => true,
        ],
    ]);
    $repositories
        ->push(['type' => 'package', 'package' => $packages])
        ->push(['packagist.org' => false]);

    File::ensureFile(
        $consumer . DIRECTORY_SEPARATOR . 'composer.json',
        HTTP::jsonEncode([
            'name' => 'bluefission/project-installer-consumer',
            'repositories' => $repositories->toArray(),
            'require' => [
                'bluefission/bluecore' => '0.0.0',
                'bluefission/project-installer-fixture' => $version,
            ],
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
            'config' => [
                'allow-plugins' => ['bluefission/bluecore' => true],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        true
    );
}

/**
 * @return array{exitCode: int, output: string}
 */
function runProcess(array $command, string $cwd, bool $allowFailure = false): array
{
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['redirect', 1],
    ], $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start process: ' . Arr::make($command)->join(' ')->val());
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exitCode = proc_close($process);
    $result = Arr::make(['exitCode' => $exitCode, 'output' => $output])->toArray();

    if (Flag::isFalse($allowFailure) && $exitCode !== 0) {
        throw new RuntimeException("Process failed:\n{$output}");
    }

    return $result;
}

function assertProcessSucceeded(array $result, string $operation): void
{
    if ($result['exitCode'] !== 0) {
        throw new RuntimeException("Composer {$operation} failed:\n{$result['output']}");
    }
}

function assertCleanComposerOutput(array $result, string $operation): void
{
    $output = Str::make($result['output']);
    foreach (Arr::make(['Fatal Error:', 'autoload_real.php', 'another package operation']) as $diagnostic) {
        if ($output->has($diagnostic)) {
            throw new RuntimeException("Composer {$operation} emitted {$diagnostic}:\n{$result['output']}");
        }
    }
}

function assertFile(string $path, string $expected): void
{
    if (!(new File())->isReachable($path)) {
        throw new RuntimeException("Expected file is not reachable: {$path}");
    }

    $actual = File::readContents($path);
    if (!Str::match($actual, $expected)) {
        throw new RuntimeException("Unexpected contents for {$path}: {$actual}");
    }
}

function fileUrl(string $path): string
{
    $normalized = Str::replace(Path::normalize($path), DIRECTORY_SEPARATOR, '/');

    return Str::startsWith($normalized, '/') ? "file://{$normalized}" : "file:///{$normalized}";
}

function composerCommand(string $binary, array $arguments): array
{
    $command = Str::endsWith(Str::lower($binary), '.phar')
        ? [PHP_BINARY, $binary]
        : [$binary];

    return Arr::make($command)->merge($arguments)->values()->toArray();
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir()
            ? rmdir($item->getPathname())
            : (chmod($item->getPathname(), 0666) && unlink($item->getPathname()));
    }
    rmdir($path);
}
