<?php
namespace BlueFission\BlueCore\Datasource;

use BlueFission\Connections\Database\MySQLLink;

/**
 * Class Delta
 *
 * This class provides a means to change and revert changes to a database using a MySQLLink connection.
 *
 * @package BlueFission\BlueCore\Datasource
 */
class Delta {

	/**
	 * Delta constructor.
	 *
	 * @param MySQLLink $link A MySQLLink object representing a database connection.
	 */
	public function __construct( MySQLLink $link )
	{
		$link->open();
	}

	/**
	 * change
	 *
	 * Makes changes to the database using the connection passed to the constructor.
	 */
	public function change()
	{

	}

	/**
	 * revert
	 *
	 * Reverts changes made to the database using the connection passed to the constructor.
	 */
	public function revert()
	{

	}
}
