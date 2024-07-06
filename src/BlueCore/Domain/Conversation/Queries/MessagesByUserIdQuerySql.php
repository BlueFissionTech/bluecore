<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Domain\Conversation\Models\MessageModel as Model;

class MessagesByUserIdQuerySql implements IMessagesByUserIdQuery
{
    private $_model;

    public function __construct(MysqlLink $link, Model $model)
    {
        $link->open();
        $this->_model = $model;
    }

    public function fetch($userId, $limit)
    {
        $model = $this->_model;
        $model->clear();
        $model->condition('user_id', '=', $userId);
        $model->condition('private', '=', 0);
        $model->limit($limit);
        $model->orderBy('timestamp', 'ASC');
        $model->read();
        $data = $model->result()->toArray();
        return $data;
    }
}
