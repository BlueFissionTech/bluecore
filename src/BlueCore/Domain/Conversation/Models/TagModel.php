<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class TagModel extends Model {
	
	protected $_table = 'tags';
	protected $_fields = [
		'tag_id',
		'label',
	];
}