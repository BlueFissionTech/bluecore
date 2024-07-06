<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Domain\Conversation\Models\DialogueModel as Model;

use BlueFission\BlueCore\Domain\Conversation\Queries\IDialoguesByTopicQuery;

class DialoguesByTopicQuerySql implements IDialoguesByTopicQuery {
	private $_model;

	public function __construct( MysqlLink $link, Model $model )
	{
		$link->open();

		$this->_model = $model;
	}

	public function fetch($topic_id) 
	{
		$model = $this->_model;
		$model->clear();
		$model->topic_id = $topic_id;
		$model->read();
		$data = $model->result()->toArray();
		return $data;
	}
}