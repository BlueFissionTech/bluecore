<?php
namespace BlueFission\BlueCore\Business\Managers;

use BlueFission\Services\Service;
use BlueFission\BlueCore\Generation\GenerationFactory;

class CodeGenerationManager extends Service {

	private $_factory;

	public function __construct( GenerationFactory $factory )
	{
		$this->_factory = $factory;
		parent::__construct();
	}

	public function addGenerator($name, $class)
	{

	}
}