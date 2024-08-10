<?php
namespace BlueFission\BlueCore\Domain\Queries;

use BlueFission\Connections\Database\MySQLLink;

class GenericQuerySql implements IGenericQuery {
    private $_model;

    public function __construct(MySQLLink $link, $model)
    {
        $link->open();

        $this->_model = $model;
    }

    public function fetch() 
    {
        $model = $this->_model;
        $model->read();
        $data = $model->result()->toArray();
        return $data;
    }
}