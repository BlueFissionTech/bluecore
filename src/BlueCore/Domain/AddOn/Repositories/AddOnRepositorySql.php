<?php
namespace BlueFission\BlueCore\Domain\AddOn\Repositories;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\AddOn\Repositories\IAddOnRepository;
use BlueFission\BlueCore\Domain\AddOn\Models\AddOnModel as Model;
use BlueFission\BlueCore\Domain\AddOn\AddOn;

class AddOnRepositorySql extends RepositorySql implements IAddOnRepository
{
    protected $_name = "addons";

    public function __construct(MysqlLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($addon_id)
    {
        $this->_model->addon_id = $addon_id;
        $this->_model->read();

        return $this->_model->response();
    }

    public function save(AddOn $addon)
    {
        $this->_model->assign($addon);
        $this->_model->write();

        return $this->_model->response();
    }

    public function remove($addon_id)
    {
        $this->_model->addon_id = $addon_id;
        $this->_model->delete();
    }

    public function lastInsertId()
    {
        $this->_model->id();
    }
}