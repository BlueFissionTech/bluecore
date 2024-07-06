<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

interface IMessagesByUserIdQuery
{
    public function fetch($userId, $limit);
}