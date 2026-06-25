<?php
/**
 * Use two classes from the BlueFission library
 */
use BlueFission\HTML\Template;
use BlueFission\Services\Response;
use BlueFission\Net\HTTP;
use BlueFission\Arr;
use BlueFission\Str;
use BlueFission\Val;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;

/**
 * Define the template function.
 *
 * @param string $file Name of the file to load
 * @param array $data Data to be passed to the template
 * @return string The rendered template
 */
if (!function_exists( 'template' )) {
	function template(string $themeName, string $file, array $data = []) {
		$app = instance();
		$theme = $app->theme($themeName);
		if (!$theme) {
			throw new \Exception("Theme '$themeName' not found.");
		}

		// $path = dirname(getcwd()).DIRECTORY_SEPARATOR.'resource'.DIRECTORY_SEPARATOR.'markup'.DIRECTORY_SEPARATOR.$file;
		// $module_path = dirname(getcwd()).DIRECTORY_SEPARATOR.'resource'.DIRECTORY_SEPARATOR.'markup'.DIRECTORY_SEPARATOR.'modules';
		store('asset_dir', $theme->location);
		$path = $theme->location.$file;
		$module_path = $theme->location.'modules';

		$template = new Template();
		
		// Configure the directory for template modules
		$template->config('module_directory', $module_path);

		// Load the template file
		$template->load($path);

		// Pass the data to the template
		$template->field($data);

		// Render the template
		$output = $template->render();

		// $template->cache();
		return $output;
	}
}

/**
 * Define the get_template function
 *
 * @param string $file Name of the file to include
 * @param array $values Values to be passed to the template
 */
if (!function_exists( 'get_template' )) {
	function get_template( $file, $values = [] ) {
		foreach ( $values as $var=>$value ) {
			$$var = $value;
		}
		
		// Get the path of the template file
		$template = get_template_path($file);
		
		// Include the template file
		include_once($template);
	}
}

/**
 * Define the get_template_path function
 *
 * @param string $file Name of the file to include
 * @return string The path of the template file
 */
if (!function_exists( 'get_template_path' )) {
	function get_template_path( $file ) {
		$template_dir = template_base_dir(template_caller_dir());
		$file = template_safe_file($file);
		$custom = Path::normalize($template_dir . DIRECTORY_SEPARATOR . 'custom' . DIRECTORY_SEPARATOR . $file);

		if ((new File())->exists($custom)) {
		    return $custom;
		}

		return Path::normalize($template_dir . DIRECTORY_SEPARATOR . $file);
	}
}

if (!function_exists('template_caller_dir')) {
	function template_caller_dir(): string
	{
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		foreach ($trace as $frame) {
			$file = $frame['file'] ?? '';
			if (Val::isNotEmpty($file) && Path::normalize($file) !== Path::normalize(__FILE__)) {
				return dirname($file);
			}
		}

		return getcwd();
	}
}

if (!function_exists('template_base_dir')) {
	function template_base_dir(string $callerDir): string
	{
		$dir = Path::normalize($callerDir);
		$segments = Arr::toArray(Str::split($dir, DIRECTORY_SEPARATOR), true);
		$markupIndex = Arr::search('markup', $segments, true);

		if ($markupIndex !== false) {
			$baseSegments = Arr::make($segments)
				->slice(0, $markupIndex + 1);

			return Path::normalize(implode(DIRECTORY_SEPARATOR, $baseSegments));
		}

		return Path::normalize(resolve_path('resource' . DIRECTORY_SEPARATOR . 'markup'));
	}
}

if (!function_exists('template_safe_file')) {
	function template_safe_file(string $file): string
	{
		$normalized = Path::normalize($file);
		if (Val::isEmpty($normalized)) {
			throw new \InvalidArgumentException('Template file cannot be empty.');
		}

		if (preg_match('#^(?:[A-Za-z]:)?[\\\\/]#', $normalized) === 1) {
			throw new \InvalidArgumentException('Template file must be relative.');
		}

		$segments = Arr::toArray(Str::split($normalized, DIRECTORY_SEPARATOR), true);
		foreach ($segments as $segment) {
			if (Val::isEmpty($segment) || $segment === '..') {
				throw new \InvalidArgumentException('Template file cannot contain traversal segments.');
			}
		}

		return Path::normalize(implode(DIRECTORY_SEPARATOR, $segments));
	}
}

/**
 * Define the template directory
 */
if (!function_exists( 'template_dir' )) {
	/**
	 * Returns the directory path of the current template
	 * 
	 * @return string The directory path of the current template
	 */
	function template_dir( ) {
		$dir = Str::replace(SITE_ROOT, '', __DIR__);
		$dir = ROOT_URL . $dir;
		return $dir;
	}
}

/**
 * Get the URL for a specific template file
 */
if (!function_exists( 'get_template_url' )) {
	/**
	 * Returns the URL for a specific template file
	 * 
	 * @param string $file The name of the template file
	 * 
	 * @return string The URL of the template file
	 */
	function get_template_url( $file ) {
		$template = get_template_path($file);
		$siteRoot = defined('SITE_ROOT') ? Path::normalize(SITE_ROOT) : Path::normalize(getcwd());
		$url = Str::replace($template, $siteRoot, '');
		$url = Str::replace(Path::normalize($url), DIRECTORY_SEPARATOR, '/');

		return '/' . ltrim($url, '/');
	}
}

/**
 * Respond to a request with JSON data
 * 
 * @param mixed $data The data to be returned in the response
 * 
 * @return string The JSON representation of the response data
 */
function response($data, $status = 200) {
	$response = new Response();

	$response->fill($data);
	$statusLine = HTTP::statusLine((int)$status);
	if (Val::isNotEmpty($statusLine)) {
		header($statusLine, true, (int)$status);
	}

	header(HTTP::headerLine('Content-Type', 'application/json'));
	return $response->send();
}

/**
 * Redirect
 *
 * @param string $location The location to redirect to
 *
 */
function redirect($location) {
	header(HTTP::headerLine('Location', $location));
}
