<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class TopicRouteModel extends Model {
	
	protected $_table = 'topic_routes';
	protected $_fields = [
		'topic_route_id',
		'from',
		'to',
		'weight',
	];
}