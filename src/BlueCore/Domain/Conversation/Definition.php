<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class Definition extends ValueObject {
	public $defintition_id;
	public $entity_id;
	public $property;
	public $verb_id;
	public $value;
}