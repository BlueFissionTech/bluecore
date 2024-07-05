<?php

namespace BlueFission\BlueCore\Model;

use BlueFission\BlueCore\Model\BaseModel;
use BlueFission\Data\Storage\Session;

/**
 * Class ModelSession
 *
 * @package BlueFission\BlueCore\Model
 *
 * This class is a subclass of BaseModel and provides session-based data storage
 */
class ModelSession extends BaseModel {
	/**
	 * @var string $_domain Domain name of the session data
	 */
	protected $_domain = '';
	
	/**
	 * @var string $_name Name of the session data
	 */
	protected $_name = '';
	
	/**
	 * @var array $_fields Fields of the session data
	 */
	protected $_fields = [];

	/**
	 * ModelSession constructor.
	 *
	 * Initializes the session data storage object, sets the type of model,
	 * activates the data object and calls the init method
	 */
	public function __construct()
	{
		$this->_type = get_class($this);
		$this->_dataObject = new Session([
			'location'=>$this->_domain,
			'name'=>$this->_name,
			'fields'=>$this->_fields
		]);
		$this->_dataObject->activate();
		$this->init();
	}

	/**
	 * Initialize the model
	 *
	 * This method sets the default value of each field in the model to null.
	 */
	protected function init()
	{
		foreach ($this->_fields as $field) {
			$this->_dataObject->$field = null;
		}
	}

	/**
	 * Write the session data
	 *
	 * This method generates a unique id for the session data if it doesn't exist
	 * and then calls the parent method to write the data to storage.
	 */
	public function write()
	{
		$id = $this->_idField;
		if ( !$this->$id ){
			parent::generateUuid();
		}
		parent::write();
	}
}
