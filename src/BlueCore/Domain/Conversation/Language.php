<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class Language extends ValueObject {
	public $language_id;
	public $name;
	public $label;
}