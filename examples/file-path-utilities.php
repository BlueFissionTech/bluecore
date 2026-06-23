<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use BlueFission\Utils\File;
use BlueFission\Utils\Path;

$runtime = bluecore_example_runtime_path('file-path');
$notesPath = $runtime . DIRECTORY_SEPARATOR . 'notes' . DIRECTORY_SEPARATOR . 'readme.txt';

$createdPath = File::ensureFile($notesPath, "created\n");
$keptPath = File::ensureFile($notesPath, "ignored\n");
$keptContents = trim((string)file_get_contents($keptPath));

$writtenPath = File::writeAtomic($notesPath, "updated\n");
$writtenContents = trim((string)file_get_contents($writtenPath));

bluecore_example_json([
    'runtime' => Path::normalize($runtime),
    'file' => Path::normalize($writtenPath),
    'created' => file_exists($createdPath),
    'ensure_file_kept_existing_contents' => $keptContents === 'created',
    'atomic_write_contents' => $writtenContents,
]);
