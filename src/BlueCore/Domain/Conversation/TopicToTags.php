<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class TopicToTags extends ValueObject {
	public $context_id;
	public $tag_id;
	public $weight;
}