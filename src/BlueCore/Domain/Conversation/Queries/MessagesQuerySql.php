<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Domain\Conversation\Models\MessageModel as Model;

class MessagesQuerySql implements IMessagesQuery
{
    private $_model;

    public function __construct(MysqlLink $link, Model $model)
    {
        $link->open();
        $this->_model = $model;
    }

    public function fetchRecent($limit)
    {
        $model = $this->_model;
        $model->clear();
        $model->condition('private', '=', 1);
        $model->limit($limit);
        $model->orderBy('timestamp', 'DESC');
        $model->read();
        $data = $model->result()->toArray();
        return $data;
    }
}
