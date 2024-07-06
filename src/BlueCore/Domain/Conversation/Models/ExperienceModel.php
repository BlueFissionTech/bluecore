<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class ExperienceModel extends Model {
	
	protected $_table = 'frames';
	protected $_fields = [
		'experience_id',
		'input_id',
		'timestamp',
		'data'
	];
}