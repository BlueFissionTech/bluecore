<?php
namespace BlueFission\BlueCore;

use BlueFission\Arr;
use BlueFission\Services\Authenticator;
use BlueFission\Data\Storage\Storage;
use BlueFission\BlueCore\Security;



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
			return Security::verifyToken($hash, $password);
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
	    $permissions = Arr::toArray($this->_data['permissions'] ?? [], true);

	    return Arr::has($permissions, $permission, true);
	}
}
