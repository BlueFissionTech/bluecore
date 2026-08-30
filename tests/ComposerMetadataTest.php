<?php

declare(strict_types=1);

namespace BlueFission\Tests;

use BlueFission\Arr;
use BlueFission\Net\HTTP;
use BlueFission\Str;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use PHPUnit\Framework\TestCase;

final class ComposerMetadataTest extends TestCase
{
    private const PACKAGE_REPOSITORY = 'https://github.com/BlueFissionTech/bluecore';

    private function composer(): Arr
    {
        $decoded = HTTP::jsonDecode(
            File::readContents($this->rootFile('composer.json')),
            true,
            []
        );

        $this->assertIsArray($decoded);

        return Arr::make($decoded);
    }

    private function rootFile(string $relativePath): string
    {
        return Path::normalize(
            Path::parentPath(__DIR__) . DIRECTORY_SEPARATOR . $relativePath
        );
    }

    public function testPackageMetadataIsReadyForPackagist(): void
    {
        $composer = $this->composer();

        $this->assertSame('bluefission/bluecore', $composer['name']);
        $this->assertSame('composer-plugin', $composer['type']);
        $this->assertSame(self::PACKAGE_REPOSITORY, $composer['homepage']);
        $this->assertSame(self::PACKAGE_REPOSITORY . '/issues', $composer['support']['issues']);
        $this->assertSame(self::PACKAGE_REPOSITORY, $composer['support']['source']);
        $this->assertTrue($composer['config']['platform-check']);
        $this->assertTrue($composer['extra']['plugin-modifies-install-path']);
    }

    public function testReleaseUsesCurrentDevElationConstraint(): void
    {
        $composer = $this->composer();

        $this->assertSame('^1.3.41', $composer['require']['bluefission/develation']);
    }

    public function testPackageArchiveExcludesDevelopmentFiles(): void
    {
        $exclude = Arr::make($this->composer()['archive']['exclude']);

        foreach (Arr::make([
            '/.github',
            '/AGENTS.md',
            '/composer.lock',
            '/examples',
            '/scripts',
            '/tests',
            '/vendor',
        ]) as $path) {
            $this->assertTrue($exclude->has($path), "Archive must exclude {$path}.");
        }
    }

    public function testPackagistWorkflowUsesSupportedUpdateContract(): void
    {
        $workflow = Str::make(
            File::readContents($this->rootFile('.github/workflows/packagist.yaml'))
        );

        foreach (Arr::make([
            'PACKAGIST_PACKAGE_URL: https://packagist.org/packages/bluefission/bluecore',
            'PACKAGIST_REPOSITORY: https://github.com/BlueFissionTech/bluecore',
            'api/update-package?username=${PACKAGIST_USERNAME}&apiToken=${PACKAGIST_TOKEN}',
            'repository',
            'url',
            '${repository}',
            '--user-agent "BlueFissionTech/bluecore Packagist workflow"',
            'Retrying Packagist update using the repository URL.',
        ]) as $expected) {
            $this->assertTrue($workflow->has($expected), "Workflow is missing {$expected}.");
        }
    }

    public function testReleaseDocumentationUsesAlphaConstraint(): void
    {
        $readme = Str::make(File::readContents($this->rootFile('README.md')));
        $release = Str::make(
            File::readContents($this->rootFile('docs/releases/v0.1.3-alpha.md'))
        );

        $this->assertTrue(
            $readme->has('composer require bluefission/bluecore:^0.1.3@alpha')
        );
        $this->assertTrue($release->has('bluefission/bluecore:^0.1.3@alpha'));
        $this->assertTrue($release->has('DevElation `^1.3.41`'));
        $this->assertTrue($release->has('Packagist resolves the tag to the same commit'));
    }

    public function testContinuousIntegrationCoversSupportedPhpVersions(): void
    {
        $workflow = Str::make(
            File::readContents($this->rootFile('.github/workflows/ci.yml'))
        );

        $this->assertTrue($workflow->has("'8.2'"));
        $this->assertTrue($workflow->has("'8.3'"));
        $this->assertTrue($workflow->has("composer-version: '2.2'"));
        $this->assertTrue($workflow->has("composer-version: 'latest'"));
        $this->assertTrue($workflow->has('tools: composer:${{ matrix.composer-version }}'));
    }
}
