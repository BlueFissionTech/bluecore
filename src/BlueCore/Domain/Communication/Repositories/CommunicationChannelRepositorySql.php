<?php
namespace BlueFission\BlueCore\Domain\Communication\Repositories;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\Communication\Repositories\ICommunicationChannelRepository;
use BlueFission\BlueCore\Domain\Communication\Models\CommunicationChannelModel as Model;
use BlueFission\BlueCore\Domain\Communication\CommunicationChannel;

class CommunicationChannelRepositorySql extends RepositorySql implements ICommunicationChannelRepository
{
    protected $_name = "communication_channels";

    public function __construct(MySQLLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($communication_channel_id)
    {
        $this->_model->assign(['communication_channel_id' => $communication_channel_id]);
        $this->_model->read();

        return $this->_model->response();
    }

    public function findByName($name)
    {
        $this->_model->assign(['name' => $name]);
        $this->_model->read();

        return $this->_model->response();
    }

    public function save(CommunicationChannel $communication_channel)
    {
        $this->_model->assign($communication_channel);
        $this->_model->write();

        return $this->_model->response();
    }

    public function remove($communication_channel_id)
    {
        $this->_model->assign(['communication_channel_id' => $communication_channel_id]);
        $this->_model->delete();
    }
}