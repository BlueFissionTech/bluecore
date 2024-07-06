<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class VerbModel extends Model {
	
	protected $_table = 'verbs';
	protected $_fields = [
		'verb_id',
		'name',
		'label',
	];
}