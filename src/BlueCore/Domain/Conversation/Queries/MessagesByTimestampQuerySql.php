<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Domain\Conversation\Models\MessageModel as Model;

class MessagesByTimestampQuerySql implements IMessagesByTimestampQuery
{
    private $_model;

    public function __construct(MysqlLink $link, Model $model)
    {
        $link->open();
        $this->_model = $model;
    }

    public function fetch($timestamp, $limit)
    {
        $model = $this->_model;
        $model->clear();
        $model->condition('timestamp', '>=', $timestamp);
        $model->limit($limit);
        $model->orderBy('timestamp', 'ASC');
        $model->read();
        $data = $model->result()->toArray();
        return $data;
    }
}