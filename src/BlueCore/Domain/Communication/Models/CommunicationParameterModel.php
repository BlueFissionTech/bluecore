<?php

namespace BlueFission\BlueCore\Domain\Communication\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class CommunicationParameterModel extends Model
{
    protected $_table = ['communication_parameters'];
    protected $_fields = [
        'communication_parameter_id',
        'communication_id',
        'name',
        'value',
    ];
}
