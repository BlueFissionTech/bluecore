<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Domain\Conversation\Models\DialogueModel as Model;

use BlueFission\BlueCore\Domain\Conversation\Queries\IDialoguesByKeywordsQuery;

class DialoguesByKeywordsQuerySql implements IDialoguesByKeywordsQuery {
	private $_model;

	public function __construct( MySQLLink $link, Model $model )
	{
		$link->open();

		$this->_model = $model;
	}

	public function fetch($keywords) 
	{
		$model = $this->_model;
		$model->clear();
		$model->text = trim($keywords);
		$model->condition('text', 'LIKE', explode(" ", trim($keywords)) );
		$model->read();
		$data = $model->result()->toArray();
		return $data;
	}
}