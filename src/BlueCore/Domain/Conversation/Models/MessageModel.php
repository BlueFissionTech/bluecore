<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class MessageModel extends Model {
	
	protected $_table = 'messages';
	protected $_fields = [
		'message_id',
		'conversation_id',
		'user_id',
		'topic_id',
		'private',
		'communication_id',
	];

	public function text()
	{
		return $this->descendent('BlueFission\BlueCore\Communication\Models\Communication', 'communication_id')->communication_content;
	}
}