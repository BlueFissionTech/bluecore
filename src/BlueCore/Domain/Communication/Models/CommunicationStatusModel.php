<?php

namespace BlueFission\BlueCore\Domain\Communication\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class CommunicationStatusModel extends Model
{
    protected $_table = ['communication_statuses'];
    protected $_fields = [
        'communication_status_id',
        'name',
        'label',
    ];
}
