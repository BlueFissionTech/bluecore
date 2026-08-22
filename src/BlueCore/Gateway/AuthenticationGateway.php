<?php
namespace BlueFission\BlueCore\Gateway;

use BlueFission\Services\Gateway;
use BlueFission\Services\Request;
use BlueFission\Data\Storage\{Session, Storage};
use BlueFission\BlueCore\Auth as Authenticator;
use BlueFission\Services\Application as App;
use BlueFission\Net\HTTP;

/**
 * AuthenticationGateway class for processing authentication request and managing session
 *
 * @package BlueFission\BlueCore\Gateway
 */
class AuthenticationGateway extends Gateway {
	private ?Authenticator $_authenticator;

	/**
	 * Redirection URI after authentication fails
	 *
	 * @var string
	 */
	public $_redirectUri = '/login';

	/**
	 * Initialize the Authentication Gateway class
	 */
	public function __construct(?Authenticator $authenticator = null)
	{
		$this->_authenticator = $authenticator;
	}
	
	/**
	 * Processes the authentication request, sets session if authenticated, otherwise redirects to login page
	 *
	 * @param Request $request
	 * @param array $arguments
	 */
	public function process( Request $request, &$arguments )
	{
		$auth = $this->_authenticator ?? App::makeInstance(Authenticator::class);

		if ( $auth->isAuthenticated() ) {
			$auth->setSession();
		} else {
			$auth->destroySession();
			$this->redirect();

			throw new GatewayDenied('Authentication required.', 302);
		}
	}

	/**
	 * Redirects to the login page
	 */
	public function redirect(): void
	{
		header(HTTP::headerLine('Location', $this->_redirectUri));
		http_response_code(302);
	}
}
