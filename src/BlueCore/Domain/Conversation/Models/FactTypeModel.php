<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class FactTypeModel extends Model {
	
	protected $_table = 'fact_types';
	protected $_fields = [
		'fact_type_id',
		'name',
		'label',
	];
}