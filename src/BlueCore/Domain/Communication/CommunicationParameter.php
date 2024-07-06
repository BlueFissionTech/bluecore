<?php
namespace BlueFission\BlueCore\Domain\Communication;

use BlueFission\BlueCore\ValueObject;

class CommunicationParameter extends ValueObject
{
    public $communication_parameter_id;
    public $communication_id;
    public $name;
    public $value;
}

