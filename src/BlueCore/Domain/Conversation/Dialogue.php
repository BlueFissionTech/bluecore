<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class Dialogue extends ValueObject {
	public $dialogue_id;
	public $dialogue_type_id; // statement, query, response
	public $language_id;
	public $topic_id;
	public $text;
	public $tokenized;
	public $weight;
	/*
	public sentiments;
	public expectations;
	public score;
	public tags;
	*/
}