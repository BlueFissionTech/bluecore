<?php
namespace BlueFission\BlueCore\Domain\Queries;

use BlueFission\Connections\Database\MySQLLink;

class GenericQuerySql implements IGenericQuery {
    private $_model;

    public function __construct(MySQLLink $link, $model = null)
    {
        $link->open();

        if ($model) {
            $this->_model = $model;
        }
    }

    public function fetch() 
    {
        if (!$this->_model) {
            return [];
        }

        $model = $this->_model;
        $model->read();
        $data = $model->result()->toArray();
        return $data;
    }
}