<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Domain\Conversation\Models\TagModel as Model;

use BlueFission\BlueCore\Domain\Conversation\Queries\ITagsByTopicQuery;

class TagsByTopicQuerySql implements ITagsByTopicQuery {
	private $_model;

	public function __construct( MysqlLink $link, Model $model )
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