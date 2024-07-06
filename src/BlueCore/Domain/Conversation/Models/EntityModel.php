<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class EntityModel extends Model {
	
	protected $_table = 'entities';
	protected $_fields = [
		'entity_id',
		'name',
		'label',
	];
}