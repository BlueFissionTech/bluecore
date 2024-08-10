<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Domain\Conversation\Models\TopicModel as Model;

use BlueFission\BlueCore\Domain\Conversation\Queries\IAllTopicQuery;

class AllTopicsQuerySql implements IAllTopicsQuery {
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