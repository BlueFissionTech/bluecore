<?php
namespace BlueFission\BlueCore\Domain\Communication\Repositories;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\Communication\Repositories\ICommunicationTypeRepository;
use BlueFission\BlueCore\Domain\Communication\Models\CommunicationTypeModel as Model;
use BlueFission\BlueCore\Domain\Communication\CommunicationType;

class CommunicationTypeRepositorySql extends RepositorySql implements ICommunicationTypeRepository
{
    protected $_name = "communication_types";

    public function __construct(MySQLLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($communication_type_id)
    {
        $this->_model->assign(['communication_type_id' => $communication_type_id]);
        $this->_model->read();

        return $this->_model->response();
    }

    public function findByName($name)
    {
        $this->_model->assign(['name' => $name]);
        $this->_model->read();

        return $this->_model->response();
    }

    public function save(CommunicationType $communication_type)
    {
        $this->_model->assign($communication_type);
        $this->_model->write();

        return $this->_model->response();
    }

    public function remove($communication_type_id)
    {
        $this->_model->assign(['communication_type_id' => $communication_type_id]);
        $this->_model->delete();
    }
}