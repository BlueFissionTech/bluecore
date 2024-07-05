<?php
namespace BlueFission\BlueCore;

/**
 * Class ValueObject implements IValueObject
 *
 * A class to store values as an object and access its properties
 */
class ValueObject implements IValueObject {

	/**
	 * ValueObject constructor.
	 *
	 * @param mixed|null $values An array or object with values to be assigned to the properties
	 */
	public function __construct($values = null) {
		if ($values) {
			$this->assign($values);
		}
	}

	/**
	 * Method to assign values to properties of the object
	 *
	 * @param mixed $values An array or object with values to be assigned to the properties
	 */
	public function assign($values) {
		foreach ( get_object_vars($this) as $property=>$value ) {
			$this->$property = is_object($values) ? ( $values->$property ?? $value ) : ( $values[$property] ?? $value );
		}
	}
}
