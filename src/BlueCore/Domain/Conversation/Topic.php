<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class Topic extends ValueObject {
	public $topic_id;
	public $name;
	public $label;
	public $weight;
}