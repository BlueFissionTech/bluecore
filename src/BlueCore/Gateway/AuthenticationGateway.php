<?php
namespace BlueFission\BlueCore\Gateway;

use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\Data\Storage\{Session, Storage};
use BlueFission\BlueCore\Auth as Authenticator;
use BlueFission\Services\Application as App;

/**
 * AuthenticationGateway class for processing authentication request and managing session
 *
 * @package BlueFission\BlueCore\Gateway
 */
class AuthenticationGateway extends Gateway {

	/**
	 * Redirection URI after authentication fails
	 *
	 * @var string
	 */
	public $_redirectUri = '/login';

	/**
	 * Initialize the Authentication Gateway class
	 */
	public function __construct() {}
	
	/**
	 * Processes the authentication request, sets session if authenticated, otherwise redirects to login page
	 *
	 * @param Request $request
	 * @param array $arguments
	 */
	public function process( Request $request, &$arguments )
	{
		$auth = App::makeInstance(Authenticator::class);

		if ( $auth->isAuthenticated() ) {
			$auth->setSession();
		} else {
			$auth->destroySession();
			$this->redirect();
		}
	}

	/**
	 * Redirects to the login page
	 */
	public function redirect()
	{
		header('Location: '.$this->_redirectUri);
	}
}
