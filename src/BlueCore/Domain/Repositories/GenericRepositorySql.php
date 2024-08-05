<?php
namespace BlueFission\BlueCore\Domain\Repositories;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Repository\RepositorySql;

class GenericRepositorySql extends RepositorySql implements IGenericRepository
{
    protected $_name;

    public function __construct(MysqlLink $link, $model, $name)
    {
        parent::__construct($link, $model);
        $this->_name = $name;
    }

    public function find($id)
    {
        $primaryKey = $this->_model->primaryKey();
        $this->_model->$primaryKey = $id;
        $this->_model->read();

        return $this->_model->response();
    }

    public function save($entity)
    {
        $this->_model->assign($entity);
        $this->_model->write();

        return $this->_model->response();
    }

    public function remove($id)
    {
        $primaryKey = $this->_model->primaryKey();
        $this->_model->$primaryKey = $id;
        $this->_model->delete();
    }

    public function lastInsertId()
    {
        $this->_model->id();
    }
}