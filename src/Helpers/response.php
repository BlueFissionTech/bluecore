<?php
/**
 * Use two classes from the BlueFission library
 */
use BlueFission\HTML\Template;
use BlueFission\Services\Response;

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
		$__file = func_get_arg(0);
		$trace = debug_backtrace();
		$caller_info = end($trace);
		$dir = dirname($caller_info['file']);
		// TODO: Make this more resilent against including files from and included custom directory
		// $template_dir = __DIR__. ( strpos(__DIR__, '/markup') ? "/" : "/markup/" );
		$template_dir = $dir;

		// Check if the current directory contains the /markup/custom directory
		if ( strpos($dir, '/markup/custom') ) {
			$template_dir = str_replace('/markup/custom', '/markup', $template_dir);
		} elseif ( !strpos($dir, '/markup') ) {
			// $template_dir .= '/markup';
			$template_dir = dirname(getcwd()).DIRECTORY_SEPARATOR.'resource'.DIRECTORY_SEPARATOR.'markup';
		}

		// Initialize the $template variable with an empty string
		$template = "";

		// Check if the file exists in the custom directory
		if(file_exists($template_dir.'/custom/'.$__file)) {
		    // If it exists, set the $template to the path of the file in the custom directory
		    $template = $template_dir.'/custom/'.$__file;
		} else {
		    // If it doesn't exist, set the $template to the path of the file in the main directory
		    $template = $template_dir.'/'.$__file;
		}

		// Return the final value of $template
		return $template;
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
		$dir = str_replace(SITE_ROOT, '', __DIR__);
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
		$__file = func_get_arg(0);
		$trace = debug_backtrace();
		$caller_info = end($trace);
		$dir = dirname($caller_info['file']);
		$template_dir = $dir;

		// Check if the directory contains "/markup/custom"
		if ( strpos($dir, '/markup/custom') ) {
			$template_dir = str_replace('/markup/custom', '/markup', $template_dir);
		} 
		// Check if the directory contains "/markup"
		elseif ( !strpos($dir, '/markup') ) {
			$template_dir = dirname(getcwd()).DIRECTORY_SEPARATOR.'resource'.DIRECTORY_SEPARATOR.'markup';
		}

		$template_url = str_replace(SITE_ROOT, '', $dir).'/markup';

		$url = "";
		// Check if a custom version of the template file exists
		if(file_exists($template_dir.'/custom/'.$__file)) {
			$url = $template_url.'/custom/'.$__file;
		} else {
			$url = $template_url.'/'.$__file;
		}

		return $url;
	}
}

/**
 * Respond to a request with JSON data
 * 
 * @param mixed $data The data to be returned in the response
 * 
 * @return string The JSON representation of the response data
 */
function response($data) {
	$response = new Response();

	$response->fill($data);
	
	header('Content-type: application/json');
	return $response->send();
}
