<?php

namespace BlueFission\BlueCore\Domain\AddOn\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class AddOnModel extends Model
{
    protected $_table = ['addons'];
    protected $_fields = [
        'addon_id',
        'name',
        'version',
        'is_active',
        'primary_file',
        'namespace',
        'path',
        'description',
    ];

    public function getActivatedAddOns()
    {
        $this->assign(['is_active' => 1]);
        $this->read();
        return $this->all();
    }
}
