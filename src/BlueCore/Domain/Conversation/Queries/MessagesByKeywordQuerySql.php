<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Domain\Conversation\Models\MessageModel as Model;

class MessagesByKeywordQuerySql implements IMessagesByKeywordQuery
{
    private $_model;

    public function __construct(MysqlLink $link, Model $model)
    {
        $link->open();
        $this->_model = $model;
    }

    public function fetch($keywords, $limit)
    {
        $model = $this->_model;
        $model->clear();
        $model->text = trim($keywords);
        $model->condition('text', 'LIKE', explode(" ", trim($keywords)));
        $model->limit($limit);
        $model->read();
        $data = $model->result()->toArray();
        return $data;
    }
}