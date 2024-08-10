<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\Conversation\Repositories\ILanguageRepository;
use BlueFission\BlueCore\Domain\Conversation\Models\LanguageModel as Model;
use BlueFission\BlueCore\Domain\Conversation\Language;

class LanguageRepositorySql extends RepositorySql implements ILanguageRepository
{
    protected $_name = "languages";

    public function __construct(MySQLLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($language_id)
    {
        $this->_model->read(['language_id'=>$language_id]);

        return $this->_model->response()['data'];
    }

    public function findByName($language_name)
    {
        $this->_model->read(['language_name'=>$language_name]);

        return $this->_model->response()['data'];
    }

    public function save(Language $language)
    {
        $this->_model->write($language);

        return $this->_model->response();
    }

    public function remove($language_id)
    {
        $this->_model->delete(['language_id'=>$language_id]);
    }
}