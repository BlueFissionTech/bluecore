<?php
namespace BlueFission\BlueCore\Domain\Communication\Repositories;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\Communication\Repositories\ICommunicationRepository;
use BlueFission\BlueCore\Domain\Communication\Models\CommunicationModel as Model;
use BlueFission\BlueCore\Domain\Communication\Communication;

class CommunicationRepositorySql extends RepositorySql implements ICommunicationRepository
{
    protected $_name = "communications";

    public function __construct(MysqlLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($communication_id)
    {
        $this->_model->communication_id = $communication_id;
        $this->_model->read();

        return $this->_model->response();
    }

    public function save(Communication $communication, array $attachments = [], array $parameters = [])
    {
        $this->_model->assign($communication);

        $this->_model->addAttachments($attachments);
        $this->_model->addParameters($parameters);
        $this->_model->write();

        return $this->_model->response();
    }

    public function remove($communication_id)
    {
        $this->_model->communication_id = $communication_id;
        $this->_model->delete();
    }

    public function lastInsertId()
    {
        $this->_model->id();
    }
}