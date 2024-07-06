<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class LanguageModel extends Model {
	
	protected $_table = 'languages';
	protected $_fields = [
		'language_id',
		'name',
		'label',
	];
}