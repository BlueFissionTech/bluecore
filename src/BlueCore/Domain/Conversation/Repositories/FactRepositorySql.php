<?php
namespace BlueFission\BlueCore\Domain\Conversation\Repositories;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\Conversation\Repositories\IFactRepository;
use BlueFission\BlueCore\Domain\Conversation\Models\FactModel as Model;
use BlueFission\BlueCore\Domain\Conversation\Fact;

class FactRepositorySql extends RepositorySql implements IFactRepository
{
    protected $_name = "facts";

    public function __construct(MySQLLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($fact_id)
    {
        $this->_model->read(['fact_id'=>$fact_id]);

        return $this->_model->response();
    }

    public function save(Fact $fact)
    {
        $this->_model->write($fact);

        return $this->_model->response();
    }

    public function remove($fact_id)
    {
        $this->_model->delete(['fact_id'=>$fact_id]);
    }
}