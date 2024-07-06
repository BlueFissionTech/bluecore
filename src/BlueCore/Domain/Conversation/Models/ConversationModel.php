<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class ConversationModel extends Model {
	
	protected $_table = 'conversations';
	protected $_fields = [
		'conversation_id',
	];
}