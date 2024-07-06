<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class TopicModel extends Model {
	
	protected $_table = 'topics';
	protected $_fields = [
		'topic_id',
		'name',
		'label',
		'weight'
	];

	public function routes()
	{
		return $this->associates('BlueFission\BlueCore\Domain\Conversation\Models\TopicModel', 'BlueFission\BlueCore\Domain\Conversation\Models\TopicRouteModel', 'topic_id', 'to', 'from' );
	}

	public function tags()
	{	
		return $this->associates('BlueFission\BlueCore\Domain\Conversation\Models\TagModel', 'BlueFission\BlueCore\Domain\Conversation\Models\TopicToTagsPivot', 'tag_id');
	}
}