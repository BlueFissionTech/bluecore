<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use BlueFission\Str;

$runtime = bluecore_example_runtime_path('file-path');
$notesPath = $runtime . DIRECTORY_SEPARATOR . 'notes' . DIRECTORY_SEPARATOR . Str::rand('', 10) . '.txt';

$createdPath = File::ensureFile($notesPath, "created\n");
$keptPath = File::ensureFile($notesPath, "ignored\n");
$keptContents = Str::trim(File::readContents($keptPath));

$writtenPath = File::writeAtomic($notesPath, "updated\n");
$writtenContents = Str::trim(File::readContents($writtenPath));

bluecore_example_json([
    'runtime' => Path::normalize($runtime),
    'file' => Path::normalize($writtenPath),
    'created' => (new File())->exists($createdPath),
    'ensure_file_kept_existing_contents' => $keptContents === 'created',
    'atomic_write_contents' => $writtenContents,
]);
