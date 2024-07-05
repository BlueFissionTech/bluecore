<?php
namespace BlueFission\BlueCore\Repository;

use BlueFission\Connections\Database\MysqlLink;
use BlueFission\BlueCore\Model\ModelSql as Model;

/**
 * RepositorySql Class.
 * 
 * Class that implements methods to access a MySQL database using the MysqlLink class.
 * 
 * @package BlueFission\BlueCore\Repository
 */
class RepositorySql extends BaseRepository
{
    /**
     * Constructor.
     *
     * @param MysqlLink $link   The MySQL connection object.
     * @param Model     $model  The model object.
     */
    public function __construct(MysqlLink $link, Model $model)
    {
        $link->open();
        parent::__construct($model);
    }
}
