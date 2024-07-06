<?php
namespace BlueFission\BlueCore\Domain\Communication;

use BlueFission\BlueCore\ValueObject;

class CommunicationStatus extends ValueObject
{
    const UNSENT = 'communication_status_unsent';
    const SENT = 'communication_status_sent';
    const DELIVERED = 'communication_status_delivered';
    const READ = 'communication_status_read';
    
    public $communication_status_id;
    public $name;
    public $label;
}

