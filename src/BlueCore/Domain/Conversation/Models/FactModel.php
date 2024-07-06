<?php
namespace BlueFission\BlueCore\Domain\Conversation\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;
use BlueFission\BlueCore\Domain\Conversation\Fact;

class FactModel extends Model {
	
	protected $_table = 'facts';
	protected $_fields = [
		'fact_id',
		'fact_type_id',
		'type',
		'is_negated',
		'var',
		'value',
		'privilege',
		'ttl',
	];

	protected $_related = [
		'type'
	];

	public function sentence()
	{
		$output = "";
		$output .= "{$this->var}";
		if ( $this->is_negated ) {
			if ( $this->type()->name == Fact::LIKE ) {
				$output .= " isn't {$this->type()->label}";
			} else {
				$output .= " {$this->type()->label} not";
			}
		} else {
			$output .= " {$this->type()->label}";
		}
		$output .= " {$this->value}";

		return $output;
	}
	
	public function type()
	{
		return $this->ancestor('BlueFission\BlueCore\Domain\Conversation\Models\FactTypeModel', 'fact_type_id');
	}
}