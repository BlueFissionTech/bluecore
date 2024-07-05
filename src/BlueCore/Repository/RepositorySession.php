<?php
namespace BlueFission\BlueCore\Repository;

use BlueFission\BlueCore\Model\ModelSession as Model;

/**
 * RepositorySession is a child class of BaseRepository and it allows for data to be retrieved and saved to a session.
 */
class RepositorySession extends BaseRepository
{
    /**
     * Constructs the RepositorySession class with a passed in ModelSession object.
     *
     * @param Model $model A ModelSession object that is being passed in to store and retrieve data from a session.
     */
    public function __construct(Model $model)
    {
        parent::__construct($model);
    }
}
