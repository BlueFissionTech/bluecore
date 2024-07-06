<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class EntityToEntityTypes extends ValueObject {
	public $entity_id;
	public $entity_type_id;
	public $weight;
}