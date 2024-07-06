<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

interface IMessagesByKeywordQuery
{
    public function fetch($keywords, $limit);
}