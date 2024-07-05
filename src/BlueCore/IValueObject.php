<?php
namespace BlueFission\BlueCore;

/**
 * Interface IValueObject
 *
 * This interface defines the `assign` method that must be implemented
 * by classes that want to support being assigned values.
 */
interface IValueObject {
	/**
	 * Assign values to the object.
	 *
	 * @param mixed $values An array or object containing values to be assigned to the object's properties.
	 *
	 * @return void
	 */
	public function assign( $values );
}
