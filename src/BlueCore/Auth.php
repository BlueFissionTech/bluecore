<?php
namespace BlueFission\BlueCore;

use BlueFission\Services\Authenticator;
use BlueFission\Data\Storage\Storage;

class Auth extends Authenticator
{
	protected $_data = [
		'id'=>'',
		'username'=>'',
		'displayname'=>'',
		'remember'=>'',
		'role'=>'',
		'group'=>'',
		'permissions'=>'',
	];

	public function __construct( Storage $session, Storage $datasource, $config = null )
	{
		$this->_verificationFunction = function($password, $hash) {
			return Security::verifyToken($password, $hash);
		};

		parent::__construct($session, $datasource, $config);
	}

	public function hasRole(string $role): bool
	{
	    // Assuming $this->_data['role'] stores the role of the user
	    return $this->_data['role'] === $role;
	}

	public function isInGroup(string $group): bool
	{
	    // Assuming $this->_data['group'] stores the group of the user
	    return $this->_data['group'] === $group;
	}

	public function hasPermission(string $permission): bool
	{
	    // Assuming $this->_data['permissions'] is an array of permissions
	    return in_array($permission, $this->_data['permissions']);
	}
}