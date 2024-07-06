<?php
namespace BlueFission\BlueCore\Domain\Content\Repositories;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Repository\RepositorySql;
use BlueFission\BlueCore\Domain\Content\Repositories\IContentRepository;
use BlueFission\BlueCore\Domain\Content\Models\ContentModel as Model;
use BlueFission\BlueCore\Domain\Content\Content;

class ContentRepositorySql extends RepositorySql implements IContentRepository
{
    protected $_name = "pages";

    public function __construct(MysqlLink $link, Model $model)
    {
        parent::__construct($link, $model);
    }

    public function find($page_id)
    {
        $this->_model->page_id = $page_id;
        $this->_model->read();

        return $this->_model->response();
    }

    public function save(Content $page)
    {
        $this->_model->assign($page);
        $this->_model->write();

        return $this->_model->response();
    }

    public function remove($page_id)
    {
        $this->_model->page_id = $page_id;
        $this->_model->delete();
    }

    public function lastInsertId()
    {
        $this->_model->id();
    }
}