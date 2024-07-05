<?php
namespace BlueFission\BlueCore\Datasource;

use BlueFission\Connections\Database\MysqlLink;

/**
 * Class Delta
 *
 * This class provides a means to change and revert changes to a database using a MysqlLink connection.
 *
 * @package BlueFission\BlueCore\Datasource
 */
class Delta {

	/**
	 * Delta constructor.
	 *
	 * @param MysqlLink $link A MysqlLink object representing a database connection.
	 */
	public function __construct( MysqlLink $link )
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
