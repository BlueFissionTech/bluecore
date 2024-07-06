<?php
namespace BlueFission\BlueCore\Domain\Conversation;

use BlueFission\BlueCore\ValueObject;

class Fact extends ValueObject {
	const LIKE = 'fact like';
	const IS = 'fact is';
	const NEEDS = 'fact needs';
	const MIGHT = 'fact might';
	const WILL = 'fact will';
	const CAN = 'fact can';
	const SHALL = 'fact shall';
	const MUST = 'fact must';
	const HAS = 'fact has';
	const DOES = 'fact does';

	public $fact_id;
	public $fact_type_id;
	public $is_negated;
	public $var;
	public $value;
	public $privilege;
	public $ttl;
}