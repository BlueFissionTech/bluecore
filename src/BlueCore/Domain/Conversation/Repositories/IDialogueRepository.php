<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\BlueCore\Domain\Conversation\Dialogue;
use BlueFission\BlueCore\Domain\Conversation\Models\DialogueModel;

interface IDialogueRepository
{
    public function find($id);
    public function search(Dialogue $dialogue);
    public function save(Dialogue $dialogue);
    public function remove(Dialogue $dialogue);
}