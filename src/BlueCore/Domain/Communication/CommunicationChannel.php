<?php
namespace BlueFission\BlueCore\Domain\Communication;

use BlueFission\BlueCore\ValueObject;

class CommunicationChannel extends ValueObject
{
    public $communication_channel_id;
    public $name;
    public $label;
    public $description;
}

