<?php
namespace BlueFission\BlueCore\Datasource;

use BlueFission\Connections\Database\MySQLLink;

class Generator {

	public function __construct( MySQLLink $link )
	{
		$link->open();
	}

	public function populate()
	{

	}
}