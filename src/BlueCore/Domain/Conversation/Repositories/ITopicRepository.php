<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\BlueCore\Domain\Conversation\Topic;
use BlueFission\BlueCore\Domain\Conversation\Models\TopicModel;

interface ITopicRepository
{
    public function find($id);
    public function findByName($name);
    public function findByLabel($label);
    public function save(Topic $topic);
    public function remove(Topic $topic);
}