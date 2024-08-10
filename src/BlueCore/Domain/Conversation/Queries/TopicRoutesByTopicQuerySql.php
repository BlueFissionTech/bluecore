<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Domain\Conversation\Models\TopicRouteModel as Model;

use BlueFission\BlueCore\Domain\Conversation\Queries\ITopicRoutesByTopicQuery;

class TopicRoutesByTopicQuerySql implements ITopicRoutesByTopicQuery {
	private $_model;

	public function __construct( MySQLLink $link, Model $model )
	{
		$link->open();

		$this->_model = $model;
	}

	public function fetch($topic_id) 
	{
		$model = $this->_model;
		$model->from = $topic_id;
		$model->read();
		$data = $model->result()->toArray();
		return $data;
	}
}