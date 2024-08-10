<?php
namespace BlueFission\BlueCore\Repository;

use BlueFission\Connections\Database\MySQLLink;
use BlueFission\BlueCore\Model\ModelSql as Model;

/**
 * RepositorySql Class.
 * 
 * Class that implements methods to access a MySQL database using the MySQLLink class.
 * 
 * @package BlueFission\BlueCore\Repository
 */
class RepositorySql extends BaseRepository
{
    /**
     * Constructor.
     *
     * @param MySQLLink $link   The MySQL connection object.
     * @param Model     $model  The model object.
     */
    public function __construct(MySQLLink $link, Model $model)
    {
        $link->open();
        parent::__construct($model);
    }
}
