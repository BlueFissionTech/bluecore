<?php
namespace BlueFission\BlueCore\Domain\Communication\Queries;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Domain\Communication\Models\CommunicationsModel as Model;

use BlueFission\BlueCore\Domain\Communication\Queries\IAllCommunicationsQuery;

class AllCommunicationsQuerySql implements IAllCommunicationssQuery {
	private $_model;

	public function __construct( MySQLLink $link, Model $model )
	{
		$link->open();

		$this->_model = $model;
	}

	public function fetch() 
	{
		$model = $this->_model;
		$model->read();
		$data = $model->result()->toArray();
		return $data;
	}
}