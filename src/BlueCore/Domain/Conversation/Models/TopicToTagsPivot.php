<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class TopicToTagsPivot extends Model {
	
	protected $_table = 'topic_to_tags';
	protected $_fields = [
		'topic_id',
		'tag_id',
		'weight'
	];
}