<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class DialogueType extends ValueObject {
	const STATEMENT = 'dialogue_type_statement';
	const QUERY = 'dialogue_type_query';
	const RESPONSE = 'dialogue_type_response';
	const AFFIRMATION = 'dialogue_type_affirmation';
	const NEGATION = 'dialogue_type_negation';
	const COMPLEX = 'dialogue_type_complex';
	const ABSTRACT = 'dialogue_type_abstract';
	const NARRATIVE = 'dialogue_type_narrative';

	public $dialogue_type_id;
	public $name; // statement, query, response
	public $label;
}