<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\BlueCore\Domain\Conversation\DialogueType;
use BlueFission\BlueCore\Domain\Conversation\Models\DialogueTypeModel;

interface IDialogueTypeRepository
{
    public function find($id);
    public function findByName($name);
    public function save(DialogueType $dialogue_type);
    public function remove(DialogueType $dialogue_type);
}