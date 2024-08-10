<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Domain\Conversation\Models\FactModel as Model;

use BlueFission\BlueCore\Domain\Conversation\Queries\IFactsByKeywordsQuery;

class FactsByKeywordsQuerySql implements IFactsByKeywordsQuery {
	private $_model;

	public function __construct( MySQLLink $link, Model $model )
	{
		$link->open();

		$this->_model = $model;
	}

	public function fetch($input) 
	{
		$model = $this->_model;
		$model->clear();
        $model->condition('var', 'LikE', explode(" ", trim($input)) );
        $model->condition('value', 'LikE', explode(" ", trim($input)) );
        $model->read();

		$data = $model->result()->toArray();
		return $data;
	}
}