<?php
namespace BlueFission\BlueCore;

use BlueFission\Arr;
use BlueFission\Str;

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
		$appThemeDir = resolve_path('resource'.DIRECTORY_SEPARATOR.'markup'.DIRECTORY_SEPARATOR);

		$addonThemeDir = resolve_path('addons'.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'resource'.DIRECTORY_SEPARATOR.'markup'.DIRECTORY_SEPARATOR);

		$path = $appThemeDir.Str::lower($name).DIRECTORY_SEPARATOR;

		if ( $location && $directory == 'app' ) {
			$location = $appThemeDir.$location.DIRECTORY_SEPARATOR;
		} elseif ($location) {
			$location = $addonThemeDir.$location.DIRECTORY_SEPARATOR;
		}

		$this->location = $location ? $location : $path;
	}
}
