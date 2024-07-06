<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class DialogueModel extends Model {
	
	protected $_table = 'dialogues';
	protected $_fields = [
		'dialogue_id',
		'dialogue_type_id',
		'language_id',
		'topic_id',
		'text',
		'tokenzied',
		'weight',
	];
}