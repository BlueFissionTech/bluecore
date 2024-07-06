<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\BlueCore\Domain\Conversation\Message;
use BlueFission\BlueCore\Domain\Conversation\Models\MessageModel;

interface IMessageRepository
{
    public function find($id);
    public function save(Message $message);
    public function remove(Message $message);
}