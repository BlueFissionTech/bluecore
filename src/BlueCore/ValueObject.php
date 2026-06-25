<?php
namespace BlueFission\BlueCore;

use BlueFission\Arr;
use BlueFission\Val;

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
		if (Val::isNotEmpty($values)) {
			$this->assign($values);
		}
	}

	/**
	 * Method to assign values to properties of the object
	 *
	 * @param mixed $values An array or object with values to be assigned to the properties
	 */
	public function assign($values) {
		$incoming = Arr::toArray((array)$values, true);

		foreach (Arr::toArray(get_object_vars($this), true) as $property=>$value) {
			$this->$property = Arr::hasKey($incoming, $property) ? $incoming[$property] : $value;
		}
	}
}
