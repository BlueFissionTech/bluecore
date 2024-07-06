<?php

namespace BlueFission\BlueCore\Domain\Communication\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class CommunicationTypeModel extends Model
{
    protected $_table = ['communication_types'];
    protected $_fields = [
        'communication_type_id',
        'name',
        'label',
    ];
}
