<?php
namespace BlueFission\BlueCore;

use BlueFission\Arr;
use BlueFission\Data\FileSystem;
use BlueFission\Str;
use BlueFission\Utils\Path;

class Theme 
{
	public $name;
	public $path;
	public $location;

	public function __construct( $name, $location = null )
	{
		$this->name = $name;

		$nameParts = Arr::toArray(Str::split($name, '/'), true);
		$directory = $nameParts[0] ?? "";
		$appThemeDir = $this->resolveDirectory(
			'resource'.DIRECTORY_SEPARATOR.'markup'.DIRECTORY_SEPARATOR
		);

		$addonThemeDir = $this->resolveDirectory(
			'addons'.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'resource'.DIRECTORY_SEPARATOR.'markup'.DIRECTORY_SEPARATOR
		);

		$path = $appThemeDir.Str::lower($name).DIRECTORY_SEPARATOR;

		if ( $location && $directory == 'app' ) {
			$location = $appThemeDir.$location.DIRECTORY_SEPARATOR;
		} elseif ($location) {
			$location = $addonThemeDir.$location.DIRECTORY_SEPARATOR;
		}

		$this->location = $location ? $location : $path;
	}

	private function resolveDirectory(string $relativePath): string
	{
		$normalizedRelativePath = Path::normalize($relativePath);
		$applicationCandidate = Path::normalize(
			(string)APP_ROOT
			.DIRECTORY_SEPARATOR
			.$normalizedRelativePath
		);

		if (FileSystem::directoryExists($applicationCandidate)) {
			return $this->withDirectorySeparator($applicationCandidate);
		}

		$resolved = Path::normalize((string)resolve_path($normalizedRelativePath));

		return $this->withDirectorySeparator($resolved);
	}

	private function withDirectorySeparator(string $path): string
	{
		$normalized = Path::normalize($path);

		return Str::endsWith($normalized, DIRECTORY_SEPARATOR)
			? $normalized
			: $normalized.DIRECTORY_SEPARATOR;
	}
}
