<?php

namespace BlueFission\BlueCore\Domain\Communication\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class CommunicationChannelModel extends Model
{
    protected $_table = ['communication_channels'];
    protected $_fields = [
        'communication_channel_id',
        'name',
        'label',
        'description',
    ];
}
