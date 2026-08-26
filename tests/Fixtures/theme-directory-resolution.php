<?php

$root = $argv[1] ?? '';
$mode = $argv[2] ?? 'directory-aware';

define('APP_ROOT', $root . DIRECTORY_SEPARATOR . 'application');
define('PROJECT_ROOT', $root . DIRECTORY_SEPARATOR . 'project');
define('SITE_ROOT', APP_ROOT);

function resolve_path($relativePath)
{
    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$relativePath);
    $applicationCandidate = APP_ROOT . DIRECTORY_SEPARATOR . trim($relativePath, DIRECTORY_SEPARATOR);
    $projectCandidate = PROJECT_ROOT . DIRECTORY_SEPARATOR . trim($relativePath, DIRECTORY_SEPARATOR);

    if (($GLOBALS['mode'] ?? '') === 'directory-aware' && is_dir($applicationCandidate)) {
        return $applicationCandidate;
    }

    if (is_file($applicationCandidate)) {
        return $applicationCandidate;
    }

    return $projectCandidate;
}

$GLOBALS['mode'] = $mode;

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use BlueFission\BlueCore\Theme;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;

$rootForTemplate = $mode === 'project-fallback' ? PROJECT_ROOT : APP_ROOT;
$template = Path::normalize(
    $rootForTemplate
    . DIRECTORY_SEPARATOR . 'addons'
    . DIRECTORY_SEPARATOR . 'vendor'
    . DIRECTORY_SEPARATOR . 'resource'
    . DIRECTORY_SEPARATOR . 'markup'
    . DIRECTORY_SEPARATOR . 'location'
    . DIRECTORY_SEPARATOR . 'login.vibe'
);

File::ensureFile($template, 'login template', true);

$theme = new Theme('vendor/theme', 'location');
$resolvedTemplate = Path::normalize($theme->location . 'login.vibe');

echo json_encode([
    'location' => Path::normalize($theme->location),
    'template' => $resolvedTemplate,
    'contents' => File::readContents($resolvedTemplate),
], JSON_THROW_ON_ERROR);
