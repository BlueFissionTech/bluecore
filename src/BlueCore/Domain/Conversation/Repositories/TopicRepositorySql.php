<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\Conversation\Repositories\ITopicRepository;
use BlueFission\BlueCore\Domain\Conversation\Models\TopicModel as Model;
use BlueFission\BlueCore\Domain\Conversation\Topic;

class TopicRepositorySql extends RepositorySql implements ITopicRepository
{
    protected $_name = "topics";

    public function __construct(MySQLLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($topic_id)
    {
        $this->_model->read(['topic_id'=>$topic_id]);

        return $this->_model->response();
    }

    public function findByName($name)
    {
        $this->_model->read(['name'=>$name]);

        return $this->_model->response();
    }

    public function findByLabel($label)
    {
        $this->_model->read(['label'=>$label]);

        return $this->_model->response();
    }

    public function save(Topic $topic)
    {
        $this->_model->write($topic);

        return $this->_model->response();
    }

    public function remove($topic_id)
    {
        $this->_model->delete(['topic_id'=>$topic_id]);
    }
}