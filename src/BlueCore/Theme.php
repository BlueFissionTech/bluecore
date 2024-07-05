<?php
namespace BlueFission\BlueCore;

class Theme 
{
	public $name;
	public $path;
	public $location;

	public function __construct( $name, $location = null )
	{
		$this->name = $name;

		$directory = explode('/', $name)[0] ?? "";
		$theme = explode('/', $name)[1] ?? "";
		$appThemeDir = OPUS_ROOT.'resource'.DIRECTORY_SEPARATOR.'markup'.DIRECTORY_SEPARATOR;

		$addonThemeDir = OPUS_ROOT.'addons'.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.'resource'.DIRECTORY_SEPARATOR.'markup'.DIRECTORY_SEPARATOR;

		$path = $appThemeDir.strtolower($name).DIRECTORY_SEPARATOR;

		if ( $location && $directory == 'app' ) {
			$location = $appThemeDir.$location.DIRECTORY_SEPARATOR;
		} elseif ($location) {
			$location = $addonThemeDir.$location.DIRECTORY_SEPARATOR;
		}

		$this->location = $location ? $location : $path;
	}
}