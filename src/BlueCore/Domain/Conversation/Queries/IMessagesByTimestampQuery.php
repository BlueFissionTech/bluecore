<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;


interface IMessagesByTimestampQuery
{
    public function fetch($timestamp, $limit);
}