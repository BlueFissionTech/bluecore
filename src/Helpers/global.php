<?php
use App\Business\Managers\CommunicationManager;
use BlueFission\Arr;
use BlueFission\Data\FileSystem;
use BlueFission\Flag;
use BlueFission\Utils\Util;
use BlueFission\Utils\File;
use BlueFission\Utils\Path;
use BlueFission\Services\Application as App;
use BlueFission\Str;
use BlueFission\Val;


if(!function_exists('import_env_vars')) {
	function import_env_vars( $file ) {
		$variables = Str::make(File::readContents($file))->splitBy('/\r\n|\r|\n/')->val();
		foreach ($variables as $var) {
			$var = Str::trim($var);
			if (Val::isEmpty($var) || Str::startsWith($var, '#') || !Str::has($var, '=')) {
				continue;
			}

			putenv($var);
			$separator = Str::pos($var, '=');
			$name = Str::sub($var, 0, $separator);
			$value = Str::sub($var, $separator + 1);
			$_ENV[$name] = $value;
		}
	}
}

if(!function_exists('env')) {
  function env($key, $default = null)
  {
      $value = getenv($key);

      if (Flag::isFalse($value)) {
          return $default;
      }
      return $value;
  }
}

if (!function_exists( 'get_site_url' )) {
	function get_site_url( $app_id = null, $path = '', $scheme = null ) {
	    if ( Val::isEmpty($app_id) && Arr::hasKey($_SERVER, 'HTTPS') && Arr::hasKey($_SERVER, 'HTTP_HOST') ) {
	        // $url = 'http://leads.local:8080';
	        $url = ( (Arr::hasKey($_SERVER, 'HTTPS') && $_SERVER['HTTPS'] )  ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
	    } else {
	        $url = '/';
	    }
	 
	    if ( Val::isNotEmpty($path) && Str::is( $path ) ) {
	        $url .= '/' . ltrim( $path, '/' );
	    }
	 
	    return $url;
	}
}

if (!function_exists( 'instance' )) {
	function instance($serviceName = '')
	{
		if ( $serviceName == '' ) {
			return App::instance();
		}
		$service = App::instance()->service($serviceName);
		return $service;
	}
}

if (!function_exists('listen')) {
	function listen($event, $callback) {
		$app = instance();
		$app->behavior($event, $callback);
	}
}


if (!defined("ROOT_URL") ){
	define('ROOT_URL', get_site_url(null, '/' . env('DASHBOARD_DIRECTORY', 'dashboard/')));
}

function prompt_silent($prompt = "Enter Password:") {
  if (preg_match('/^win/i', PHP_OS)) {
    $vbscript = sys_get_temp_dir() . 'prompt_password.vbs';
    file_put_contents(
      $vbscript, 'wscript.echo(InputBox("'
      . addslashes($prompt)
      . '", "", "password here"))');
    $command = "cscript //nologo " . escapeshellarg($vbscript);
    $password = rtrim(shell_exec($command));
    unlink($vbscript);
    return $password;
  } else {
    $command = "/usr/bin/env bash -c 'echo OK'";
    if (rtrim(shell_exec($command)) !== 'OK') {
      trigger_error("Can't invoke bash");
      return;
    }
    $command = "/usr/bin/env bash -c 'read -s -p \""
      . addslashes($prompt)
      . "\" mypassword && echo \$mypassword'";
    $password = rtrim(shell_exec($command));
    echo "\n";
    return $password;
  }
}

if (!function_exists('tell')) {
    function tell(string $content, string $channel = null, int $userId = null, array $attachments = [], array $parameters = [])
    {
        return CommunicationManager::send($content, $channel, $userId, false, $attachments, $parameters);
    }
}

if (!function_exists('ask')) {
    function ask(string $content, string $channel = null, int $userId = null, array $attachments = [], array $parameters = [])
    {
        return CommunicationManager::send($content, $channel, $userId, true, $attachments, $parameters);
    }
}

if (!function_exists('whisper')) {
	function whisper(string $content, int $userId = null, array $attachments = [], array $parameters = [])
	{
	    return CommunicationManager::send($content, null, $userId, $attachments, $parameters, true);
	}
}

if(!function_exists('resolve_path')) {
	function resolve_path($pathInProject, ?string $applicationRoot = null, ?string $legacyProjectRoot = null)
	{
	    return Path::resolveProjectPath(
	        $pathInProject,
	        $applicationRoot,
	        $legacyProjectRoot
	    );
	}
}

if (!function_exists('store')) {
	function store(string $var, mixed $value = null): mixed 
	{
		return Util::store($var, $value);
	}
}

if (!function_exists('csrf_token')) {
	function csrf_token() {
		return Util::csrfToken();
	}
}

if (!function_exists('slugify')) {
	function slugify(string $string): string {
		return Str::slugify($string);
	}
}

if (!function_exists('pluralize')) {
	function pluralize(string $string): string {
		return Str::pluralize($string);
	}
}
