<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\BlueCore\Domain\Conversation\Fact;
use BlueFission\BlueCore\Domain\Conversation\Models\FactModel;

interface IFactRepository
{
    public function find($id);
    public function save(Fact $fact);
    public function remove(Fact $fact);
}