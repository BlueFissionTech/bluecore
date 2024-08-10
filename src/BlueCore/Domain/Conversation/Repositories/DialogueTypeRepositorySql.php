<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\Conversation\Repositories\IDialogueTypeRepository;
use BlueFission\BlueCore\Domain\Conversation\Models\DialogueTypeModel as Model;
use BlueFission\BlueCore\Domain\Conversation\DialogueType;

class DialogueTypeRepositorySql extends RepositorySql implements IDialogueTypeRepository
{
    protected $_name = "dialogues";

    public function __construct(MySQLLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($dialogue_id)
    {
        $this->_model->read(['dialogue_id'=>$dialogue_id]);

        return $this->_model->response();
    }

    public function findByName($name)
    {
        $this->_model->read(['name'=>$name]);

        return $this->_model->response();
    }

    public function save(DialogueType $dialogue)
    {
        $this->_model->write($dialogue);

        return $this->_model->response();
    }

    public function remove($dialogue_id)
    {
        $this->_model->delete(['dialogue_id'=>$dialogue_id]);
    }
}