<?php
namespace BlueFission\BlueCore\Domain\Conversation\Queries;

interface IMessagesQuery
{
    public function fetchRecent($limit);
}