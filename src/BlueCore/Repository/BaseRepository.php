<?php
namespace BlueFission\BlueCore\Repository;

// use BlueFission\Data\Storage\Storage;
use BlueFission\BlueCore\Model\BaseModel;

/**
 * Class BaseRepository
 *
 * BaseRepository is the base class for all repository classes. It provides basic 
 * functions that can be used by all repository classes.
 */
class BaseRepository
{
    /**
     * @var BaseModel
     * Holds the instance of the BaseModel class.
     */
    protected $_model;
    
    /**
     * BaseRepository constructor.
     *
     * Constructs the BaseRepository object and sets the model property.
     *
     * @param BaseModel $model
     */
    public function __construct(BaseModel $model)
    {
        $this->_model = $model;
    }
}
