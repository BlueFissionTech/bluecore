<?php
namespace BlueFission\BlueCore\Datasource;

use BlueFission\Connections\Database\MysqlLink;

class Generator {

	public function __construct( MysqlLink $link )
	{
		$link->open();
	}

	public function populate()
	{

	}
}