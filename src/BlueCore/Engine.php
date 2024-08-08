<?php
namespace BlueFission\BlueCore;

use BlueFission\Services\Application;
use BlueFission\Services\Service;
use BlueFission\Services\Response;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\Utils\Loader;

/**
 * Class Engine
 *
 * The Engine class is a subclass of Application that sets up and starts the BlueFission application.
 *
 * @package BlueFission\BlueCore
 */
class Engine extends Application {

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
	 * Bootstraps the application, loading configurations and auto-discovering helpers and mappings
	 *
	 * @return Engine
	 */
	public function bootstrap() {

		$this->_loader = Loader::instance();

		$this->dispatch('OnAppInitialized');
		
		$this->loadConfiguration();

		$this->autoDiscoverHelpers();

		$this->autoDiscoverMapping();

		$this->dispatch('OnAppLoaded');

		$this->assetDir(store('asset_dir'));
		
		return $this;
	}

	/**
	 * Loads the database and application configurations
	 *
	 * @return void
	 */
	public function loadConfiguration() {
		// Paths

		$paths = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'paths.php';

		$this->_configurations['paths'] = $paths;

		// Data

		$database = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'database.php';

		$this->_configurations['database'] = $database;

		// Communication

		$communication = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'communication.php';

		$this->_configurations['communication'] = $communication;

		// Natural Langauge Processing
		
		$grammar = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'nlp'.DIRECTORY_SEPARATOR.'grammar.php';
		$dictionary = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'nlp'.DIRECTORY_SEPARATOR.'dictionary.php';
		$roots = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'nlp'.DIRECTORY_SEPARATOR.'roots.php';
		$dialogue = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'nlp'.DIRECTORY_SEPARATOR.'dialogue.php';
		$statements = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'nlp'.DIRECTORY_SEPARATOR.'statements.php';

		$this->_configurations['nlp'] = [
			'grammar' => $grammar,
			'dictionary' => $dictionary,
			'roots' => $roots,
			'dialogue' => $dialogue,
			'statements' => $statements,
		];

		// Application Logic

		$config = require OPUS_ROOT.'common'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'application.php';

		$this->_configurations['app'] = $config;

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
}