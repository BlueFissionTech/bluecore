<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class DefinitionModel extends Model {
	
	protected $_table = 'definitions';
	protected $_fields = [
		'definition_id',
		'entity_id',
		'property',
		'verb_id',
		'value',
	];
}