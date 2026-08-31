<?php
namespace BlueFission\BlueCore;

use BlueFission\Services\Application;
use BlueFission\Services\Service;
use BlueFission\Services\Response;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\DevElation as Dev;
use BlueFission\Utils\Loader;
use BlueFission\BlueCore\Security;
use BlueFission\BlueCore\Gateway\GatewayDenied;
use BlueFission\BlueCore\Hooks\LifecycleFailure;
use BlueFission\BlueCore\Registration\RegistrationResolutionException;
use BlueFission\Arr;
use BlueFission\Num;
use BlueFission\Str;
use BlueFission\Val;
use InvalidArgumentException;
use Throwable;

/**
 * Class Engine
 *
 * The Engine class is a subclass of Application that sets up and starts the BlueFission application.
 *
 * @package BlueFission\BlueCore
 */
class Engine extends Application {

	public const HOOK_BOOTSTRAP_BEFORE = 'bluecore.engine.bootstrap.before';
	public const HOOK_BOOTSTRAP_AFTER = 'bluecore.engine.bootstrap.after';
	public const HOOK_BOOTSTRAP_FAILED = 'bluecore.engine.bootstrap.failed';
	public const HOOK_PROCESS_BEFORE = 'bluecore.engine.process.before';
	public const HOOK_PROCESS_AFTER = 'bluecore.engine.process.after';
	public const HOOK_PROCESS_DENIED = 'bluecore.engine.process.denied';
	public const HOOK_PROCESS_FAILED = 'bluecore.engine.process.failed';
	public const HOOK_RUN_BEFORE = 'bluecore.engine.run.before';
	public const HOOK_RUN_AFTER = 'bluecore.engine.run.after';
	public const HOOK_RUN_DENIED = 'bluecore.engine.run.denied';
	public const HOOK_RUN_FAILED = 'bluecore.engine.run.failed';
	public const MAX_HOOK_RECURSION_DEPTH = 8;

	private const NON_RECURSIVE_HOOKS = [
		self::HOOK_BOOTSTRAP_FAILED,
		self::HOOK_PROCESS_FAILED,
		self::HOOK_RUN_FAILED,
	];

	/**
	 * The active Engine instance for each concrete application class.
	 *
	 * @var array<class-string, Engine>
	 */
	private static array $_activeInstances = [];

	/**
	 * The loader object
	 *
	 * @var Loader
	 */
	private $_loader;

	/**
	 * An array of registered extensions
	 *
	 * @var array
	 */
	private $_extensions = [];

	/**
	 * An array of registered themes
	 *
	 * @var array
	 */
	private $_themes = [];

	/**
	 * An array of configurations for the application
	 *
	 * @var array
	 */
	private $_configurations = [];

	/**
	 * The session object
	 *
	 * @var Session
	 */
	private $_session;

	private ?GatewayDenied $_gatewayDenial = null;

	private array $_activeHookDepths = [];

	private array $_hookDepthLimits = [];

	public function __construct($config = [])
	{
		parent::__construct($config);

		self::$_activeInstances[static::class] = $this;
	}

	/**
	 * Return the active instance for the concrete Engine class.
	 */
	public static function instance()
	{
		$calledClass = static::class;
		$instances = Arr::make(self::$_activeInstances);

		if ($instances->hasKey($calledClass)) {
			return $instances->get($calledClass);
		}

		$instance = parent::getInstance($calledClass);
		if (!$instance instanceof $calledClass) {
			throw new \LogicException("Application instance '{$calledClass}' is not compatible with the active Engine class.");
		}

		self::$_activeInstances[$calledClass] = $instance;

		return $instance;
	}

	/**
	 * Resolve a dependency from the active concrete Engine instance.
	 */
	public static function makeInstance(string $class)
	{
		return static::instance()->resolveForPhase($class, 'static');
	}

	public function resolve(string $class)
	{
		return $this->resolveForPhase($class);
	}

	/**
	 * Resolve a root contract while retaining lifecycle-phase diagnostics.
	 */
	public function resolveForPhase(string $class, string $phase = 'runtime')
	{
		$contract = Str::trim($class);
		$lifecyclePhase = Str::trim($phase);
		$bindings = Arr::make($this->_bindings);
		$implementation = $bindings->hasKey($contract)
			? $bindings->get($contract)
			: $contract;

		if (Val::isEmpty($contract) || Val::isEmpty($lifecyclePhase)) {
			throw new \InvalidArgumentException('Dependency contract and lifecycle phase cannot be empty.');
		}

		if (!Str::is($implementation) || Val::isEmpty($implementation)) {
			throw RegistrationResolutionException::forBinding(
				$contract,
				$lifecyclePhase,
				$implementation
			);
		}

		try {
			return parent::resolve($implementation);
		} catch (\Throwable $exception) {
			throw RegistrationResolutionException::forBinding(
				$contract,
				$lifecyclePhase,
				$implementation,
				$exception
			);
		}
	}

	public function process()
	{
		$this->_gatewayDenial = null;
		$this->dispatchHook(self::HOOK_PROCESS_BEFORE, [$this]);

		try {
			parent::process();
		} catch (GatewayDenied $denial) {
			$this->_gatewayDenial = $denial;
			$this->dispatchHook(self::HOOK_PROCESS_DENIED, [$denial, $this]);
		} catch (Throwable $exception) {
			$this->dispatchFailureHook(self::HOOK_PROCESS_FAILED, 'process', $exception);
			throw $exception;
		}

		$this->dispatchHook(self::HOOK_PROCESS_AFTER, [$this]);

		return $this;
	}

	public function run()
	{
		if ($this->denied()) {
			$this->dispatchHook(self::HOOK_RUN_DENIED, [$this->_gatewayDenial, $this]);
			return $this;
		}

		$this->dispatchHook(self::HOOK_RUN_BEFORE, [$this]);

		try {
			$result = parent::run();
		} catch (Throwable $exception) {
			$this->dispatchFailureHook(self::HOOK_RUN_FAILED, 'run', $exception);
			throw $exception;
		}

		$this->dispatchHook(self::HOOK_RUN_AFTER, [$result, $this]);

		return $result;
	}

	public function denied(): bool
	{
		return Val::isNotNull($this->_gatewayDenial);
	}

	public function denial(): ?GatewayDenied
	{
		return $this->_gatewayDenial;
	}

	/**
	 * Bootstraps the application, loading configurations and auto-discovering helpers and mappings
	 *
	 * @return Engine
	 */
	public function bootstrap() {
		$this->dispatchHook(self::HOOK_BOOTSTRAP_BEFORE, [$this]);

		try {
			Security::init();

			$this->_loader = Loader::instance();

			$this->dispatch('OnAppInitialized');
		
			$this->loadConfiguration();

			$this->autoDiscoverHelpers();

			$this->autoDiscoverMapping();

			$this->dispatch('OnAppLoaded');

			$this->assetDir(store('asset_dir'));

			$this->_session = instance('session');
		} catch (Throwable $exception) {
			$this->dispatchFailureHook(self::HOOK_BOOTSTRAP_FAILED, 'bootstrap', $exception);
			throw $exception;
		}

		$this->dispatchHook(self::HOOK_BOOTSTRAP_AFTER, [$this]);
		
		return $this;
	}

	public function hookRecursionDepth(string $hook, int $depth = 1): self
	{
		if (!Str::startsWith($hook, 'bluecore.engine.')) {
			throw new InvalidArgumentException('Engine hook recursion can only be configured for Engine-owned hooks.');
		}

		if ($depth < 1 || $depth > self::MAX_HOOK_RECURSION_DEPTH) {
			throw new InvalidArgumentException(
				'Engine hook recursion depth must be between 1 and ' . self::MAX_HOOK_RECURSION_DEPTH . '.'
			);
		}

		if ($depth > 1 && Arr::has(self::NON_RECURSIVE_HOOKS, $hook)) {
			throw new InvalidArgumentException('Engine failure hooks cannot opt into recursive dispatch.');
		}

		$this->_hookDepthLimits[$hook] = $depth;

		return $this;
	}

	private function dispatchHook(string $hook, array $arguments = []): void
	{
		$currentDepth = (int)($this->_activeHookDepths[$hook] ?? 0);
		$allowedDepth = (int)($this->_hookDepthLimits[$hook] ?? 1);

		if ($currentDepth >= $allowedDepth) {
			return;
		}

		$this->_activeHookDepths[$hook] = (int)Num::make($currentDepth)->increment()->val();

		try {
			Dev::do($hook, $arguments);
		} finally {
			$remainingDepth = (int)Num::make($this->_activeHookDepths[$hook])->decrement()->val();

			if ($remainingDepth <= 0) {
				unset($this->_activeHookDepths[$hook]);
			} else {
				$this->_activeHookDepths[$hook] = $remainingDepth;
			}
		}
	}

	private function dispatchFailureHook(string $hook, string $operation, Throwable $exception): void
	{
		$this->dispatchHook($hook, [
			new LifecycleFailure($hook, $operation, (string)$this->name(), $exception),
		]);
	}

	/**
	 * Loads the database and application configurations
	 *
	 * @return void
	 */
	public function loadConfiguration() {
		// glob the config directory
		$configFiles = glob(resolve_path('common/config/*.php'));

		foreach ( $configFiles as $file ) {
			// get the file name without the path
			$filename = basename($file);

			$config = require resolve_path('common/config/'.$filename);
			if ( is_array($config) ) {
				$this->_configurations[basename($file, '.php')] = $config;
			}
		}

		// Application level settings
		$config = $this->configuration('app');

		foreach ( $config['aliases'] as $alias=>$classname ) {
			class_alias($classname, $alias);
		}

		foreach ( $config['extensions'] as $extension ) {

			$class = new $extension();
			$class->init();
			$this->addExtension( $class->name() );
		}

		foreach ( $config['gateways'] as $name=>$gateway ) {
			$this->gateway($name, $gateway);
		}
	}

	/**
	 * Returns the configuration values for a specific key or all configuration values
	 *
	 * @param string $key The key for the configuration values to return
	 *
	 * @return mixed The configuration values for the specified key or all configuration values
	 */
	public function configuration(string $key = '', mixed $value = null)
	{
		if ( $key && $value ) {
			$this->_configurations[$key] = $value;
		} elseif ( $key ) {
			if (!isset($this->_configurations[$key]) ) {
				throw new \Exception("Configuration key '{$key}' not found.");
			}

			return $this->_configurations[$key];
		}
		return $this->_configurations;
	}

	/**
	 * Automatically discover the mapping files and load them.
	 * 
	 * @return void
	 */
	private function autoDiscoverMapping() {
		$this->_loader->load("mapping.*");
	}

	/**
	 * Automatically discover the helper files and load them.
	 * 
	 * @return void
	 */
	private function autoDiscoverHelpers() {
		$this->_loader->load("common.helpers.*");
	}

	/**
	 * Add an extension to the extensions array.
	 * 
	 * @param string $extension The name of the extension to add.
	 * 
	 * @return void
	 */
	private function addExtension( $extension ) {
		$this->_extensions[] = $extension;
	}

	/**
	 * Add a theme to the themes array.
	 * 
	 * @param string $theme The name of the theme to add.
	 * 
	 * @return void
	 */
	public function addTheme( $theme ) {
		$this->_themes[$theme->name] = $theme;
	}

	/**
	 * Get themes from the themes array.
	 * 
	 * @param string $name the name of the theme to be selected.
	 * 
	 * @return BlueFission\BlueCore\Theme
	 */
	public function theme( $name ) {
		if (!strpos($name, '/')) {
			$name = 'app/'.$name;
		}
		return $this->_themes[$name] ?? null;
	}

	/**
	 * Validate the csrf token
	 * 
	 * @return $this
	 */
	public function validateCsrf()
	{
		if ($_SERVER['REQUEST_METHOD'] == 'POST' ) {
			$csrf = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
	        $token = store('_token');

	        if (!$csrf) {
	            die('Invalid Request: Missing CSRF token');
	        } elseif (!$token) {
	            die('Invalid Request: Session expired or CSRF token missing');
	        } elseif (!hash_equals($token, $csrf)) {
	            die('Invalid Request: CSRF token mismatch');
	        }
		}
		return $this;
	}
}
