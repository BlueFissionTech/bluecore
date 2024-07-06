<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class TopicRoute extends ValueObject {
	public $context_route_id;
	public $from;
	public $to;
	public $weight;
}