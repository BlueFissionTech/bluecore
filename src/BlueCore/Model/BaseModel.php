<?php
namespace BlueFission\BlueCore\Model;

use BlueFission\Collections\Collection;
use BlueFission\Data\IData;
use BlueFission\Data\Storage\Storage;
use BlueFission\Val;
use BlueFission\Str;
use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Behavioral\Behaviors\State;
use BlueFission\BlueCore\Engine as App;
use Elasticsearch\ClientBuilder;
use JsonSerializable;

/**
 * BaseModel class serves as a base model for other models.
 * It implements IData, JsonSerializable interfaces.
 */
class BaseModel extends Obj implements IData, JsonSerializable {
	/**
     * @var Elasticsearch\Client Elasticsearch client instance.
     */
    protected $_elasticsearchClient;

	/**
	 * @var string Format for timestamp.
	 */
	protected $_timestampFormat = 'Y-m-d G:i:s';

	/**
	 * @var Storage Object that holds the data.
	 */
	protected $_dataObject;

	/**
	 * @var array List of related data.
	 */
	protected $_related = [];

	/**
	 * @var array List of relationships.
	 */
	protected $_relationships = [];

	/**
	 * @var string Name of the field for ID.
	 */
	protected $_idField = 'id';

	/**
	 * Constructor for BaseModel class.
	 *
	 * Initializes the _dataObject as a Storage object.
     * Initializes the _elasticsearchClient as an Elasticsearch client object.
	 */
	public function __construct($values = null) 
	{ 
		// parent::__construct();
		$this->assign($values);
		// Not using dependency injection because
		// 	These objects are necessarily coupled.
		// This is essentially just a container for a DB object.
		// We'll just extend new classes for new storage types;
		$this->_dataObject = new Storage();
        // $this->_elasticsearchClient = ClientBuilder::fromConfig(\App::instance()->configuration('database')['elasticsearch'];
		return $this;
	}

	/**
	 * Generates a timestamp for the data.
	 *
	 * If ID is not set, created is set to the current timestamp.
	 * Updated is always set to the current timestamp.
	 */
	protected function generateTimestamp()
	{
		$id = $this->_idField;

		if (!$this->_dataObject->$id) {
			$this->created = date($this->_timestampFormat);
		}
		$this->updated = date($this->_timestampFormat);
	}

	/**
	 * Generates a unique identifier for the data.
	 */
	protected function generateUuid()
	{
		$id = $this->_idField;
		$this->{$id} = Str::uuid4();
	}

	/**
	 * Retrieves or sets the value of a field in the data.
	 *
	 * @param string $field Name of the field.
	 * @param mixed $value Value to set the field to.
	 *
	 * @return mixed The value of the field.
	 */
	public function field(string $field, $value = null): mixed
	{
		return $this->_dataObject->field($field, $value);
	}

	/**
	 * Clears all data from the object.
	 */
	public function clear(): Obj
	{
		$this->_dataObject->clear();

		return $this;
	}

	public function limit($limit)
	{
		$this->_dataObject->limit($limit);
		return $this;
	}

	public function orderBy($field, $order = 'ASC')
	{
		$this->_dataObject->order($field, $order);
		return $this;
	}

	public function condition($field, $condition, $value)
	{
		$this->_dataObject->condition($field, $condition, $value);
		return $this;
	}

	/**
	 * Reads data from the storage.
	 */
	public function read($values = null) :Obj
	{ 
		if (Val::isNotEmpty($values)) {
			$this->assign($values);
		}

		$this->readFromElasticsearch($this->_dataObject->data());
		
		$this->_dataObject->activate();
		$this->_dataObject->read();
		$this->loadRelationships();

		return $this;
	}
	
	/**
	 * Writes data to the underlying data object.
	 * Generates a timestamp before writing to the data object.
	 *
	 * @return mixed Result of the write operation on the data object.
	 */
	public function write($values = null) : Obj
	{
		if (Val::isNotEmpty($values)) {
			$this->assign($values);
		}

		$this->generateTimestamp();
		$this->_dataObject->activate();
		$this->_dataObject->write();
		if ($this->_dataObject->status() == Storage::STATUS_SUCCESS) {
            // Write to Elasticsearch
            $this->writeToElasticsearch();
        }
		return $this;
	}

	/**
	 * Deletes data from the underlying data object.
	 *
	 * @return mixed Result of the delete operation on the data object.
	 */
	public function delete($values = null) : Obj
	{ 
		if (Val::isNotEmpty($values)) {
			$this->assign($values);
		}
		$this->_dataObject->activate();
		$this->_dataObject->delete();

		if ($this->_dataObject->status() == Storage::STATUS_SUCCESS) {
            // Delete from Elasticsearch
            $this->deleteFromElasticsearch($this->id());
        }
		return $this;
	}

	/**
	 * Returns the contents of the underlying data object.
	 *
	 * @return mixed Contents of the data object.
	 */
	public function contents() :mixed
	{ 
		return $this->_dataObject->contents();
	}

	/**
	 * Gets the status of the data object.
	 *
	 * @param string|null $message Optional message to accompany the status.
	 * @return mixed Status of the data object.
	 */
	public function status( $message = null ) :mixed
	{
		return $this->_dataObject->status();
	}

	/**
	 * Gets the children of the object.
	 *
	 * @return array Array of descendent objects.
	 */
	public function children() {
		return isset($this->_relationships['descendents']) ? $this->{$this->_relationships['descendents']->first()}() : new Collection();
	}

	/**
	 * Gets the ID of the object.
	 *
	 * @return mixed ID of the object.
	 */
	public function id() {
		if($this->_idField) {
			return $this->data()[$this->_idField];
		}
		return 0;
	}

	/**
	 * Gets the ID field of the object.
	 *
	 * @return string ID field of the object.
	 */
	public function idField() {
		return $this->_idField;
	}

	/**
	 * Gets a recordset matching all data in the model
	 *
	 * @return mixed a collection of rows
	 */
	public function all() {
		return $this->_dataObject->result();
	}

	/**
	 * Gets the data of the object.
	 *
	 * @return mixed Data of the object.
	 */
	public function data() :mixed
	{
		$data = $this->_dataObject->data()->value();
		return $data;
	}

	/**
	 * Configures the object with optional parameters.
	 *
	 * @param mixed $config Optional configuration parameters.
	 * @param mixed $value Optional value for the configuration parameters.
	 */
	public function config( $config = null, $value = null ) {}

	/**
	 * Assigns data to the object.
	 *
	 * @param mixed $data Data to be assigned to the object.
	 * @throws \InvalidArgumentException if $data is not an object or associative array.
	 */
	public function assign( $data ) : Obj
	{
		$this->clear();
		if ( is_object( $data ) || ( Val::isNotEmpty( $data ) && Arr::isAssoc( $data ) ) ) {
			foreach ( $data as $a=>$b ) {
				$this->field($a, $b);
			}
		}
		else if ( $data !== null ) {
			throw new \InvalidArgumentException( "Can't import from variable type " . gettype($data) );
		}

		return $this;
	}

	/**
	 * Gets the ancestor of the object.
	 *
	 * @param string $modelClass Class of the model object.
	 * @param string $from_id_name ID field name of the model.
	 * @param string $on_id_name Optional ID field name of the ancestor.
	 * @return mixed Ancestor object.
	 */
	protected function ancestor($modelClass, $from_id_name, $on_id_name = null)
	{
		$model = \App::makeInstance($modelClass);
		$id = $on_id_name ?? $from_id_name;
		$model->$id = $this->$from_id_name;
		$model->read();
		// $data = $model->data();
		return $model;
	}

	/**
	 * protected function descendent
	 *
	 * Create a new instance of the $modelClass and sets its $on_id_name attribute
	 * to the value of $id, which is either $from_id_name or $on_id_name.
	 * The created model's data is then read.
	 *
	 * @param string $modelClass the name of the model class
	 * @param string $on_id_name the name of the id field in the model
	 * @param string $from_id_name (optional) the name of the id field in the current instance
	 * @return object an instance of the model
	 */
	protected function descendant($modelClass, $on_id_name, $from_id_name = null)
	{
		$model = \App::makeInstance($modelClass);
		$id = $from_id_name ?? $on_id_name;
		$model->$on_id_name = $this->$id;
		$model->read();
		// $data = $model->data();
		return $model;
	}

	/**
	 * protected function descendents
	 *
	 * Create a new instance of the $modelClass and sets its $on_id_name attribute
	 * to the value of $id, which is either $from_id_name or $on_id_name.
	 * The created model's data is then read and transformed into an array of objects.
	 *
	 * @param string $modelClass the name of the model class
	 * @param string $on_id_name the name of the id field in the model
	 * @param string $from_id_name (optional) the name of the id field in the current instance
	 * @return array an array of instances of the model
	 */
	protected function descendants($modelClass, $on_id_name, $from_id_name = null)
	{
		// $refClass = new \ReflectionClass($modelClass);
		$model = \App::makeInstance($modelClass);
		$id = $from_id_name ?? $on_id_name;
		$model->$on_id_name = $this->$id;
		$model->read();
		$data = $model->result()->toArray();
		$result = new Collection();
		foreach ( $data as $row ) {
			$model->assign($row);
			$model->read();
			$result[] = clone $model;
		}
		return $result;
	}

	protected function associates($modelClass, $pivotClass, $on_id_name, $to_id_name = null, $from_id_name = null)
	{
		if ( !$this->id() ) {
			return new Collection();
		}

		$model = \App::makeInstance($modelClass);
		$pivot = \App::makeInstance($pivotClass);
		$id1 = $from_id_name ?? $this->_idField;
		$id2 = $to_id_name ?? $on_id_name;
		$pivot->$id1 = $this->id();
		$pivot->read();

		$data = $pivot->result()->toArray();

		$result = new Collection();
		foreach ( $data as $row ) {
			$model->clear();
			$model->$on_id_name = $row[$id2];
			$model->read();
			$result[] = clone $model;
		}
		return $result;
	}

	/**
	 * protected function addRelationship
	 *
	 * Adds a new relationship of type $type and named $name to the current instance.
	 *
	 * @param string $type the type of the relationship
	 * @param string $name the name of the relationship
	 */
	protected function addRelationship( $type, $name )
	{
		if ( !isset( $this->_relationships[$type]) ) {
			$this->_relationships[$type] = new Collection();
		}
		$this->_relationships[$type][] = $name;
	}

	/**
	 * protected function loadRelationships
	 *
	 * Loads all relationships defined in the _related property of the current instance.
	 */
	protected function loadRelationships()
	{
		foreach ($this->_related as $relation)
		{
			if ( method_exists($this, $relation) ) {
				$this->addRelationship('descendents', $relation);
				if ( method_exists ( $this->_dataObject, 'perform' )) {
					$this->_dataObject->perform(State::DRAFT);
				}
				// $this->{$relation} = $this->{$relation}();
				$this->field($relation, $this->{$relation}());
				if ( method_exists ( $this->_dataObject, 'halt' )) {
					$this->_dataObject->halt(State::DRAFT);
				}
			}
		}
	}

	/**
	 * Return a response for this object
	 *
	 * @return array An array with id, children, list, data and status of this object.
	 */
	public function response()
	{
		$response = [
			'id' => $this->id(),
			'children' => isset($this->_relationships['descendents']) ? $this->{$this->_relationships['descendents'][0]}() : [],
			'list' => $this->contents(),
			'data' => $this->data(),
			'status'=> $this->status(),
		];

		if ( env('DEBUG') && method_exists ( $this, 'query' )) {
			$response['info'] = $this->query();
		}
		return $response;
	}

    protected function readFromElasticsearch($searchParams = [])
	{
		if (!$this->_elasticsearchClient) {
			return;
		}
	    $query = [];

	    // Build the query based on provided search parameters
	    foreach ($searchParams as $field => $value) {
	        $query['bool']['must'][] = [
	            'match' => [
	                $field => $value
	            ]
	        ];
	    }

	    // Prepare the Elasticsearch search request
	    $params = [
	        'index' => $this->_dataObject->config('name'),
	        'body'  => [
	            'query' => $query
	        ]
	    ];

	    // Execute the search request
	    $response = $this->_elasticsearchClient->search($params);

	    // Check if any results were found
	    if ($response['hits']['total']['value'] > 0) {
	        // Assign the first result's _source to the data object
	        $this->assign($response['hits']['hits'][0]['_source']);
	    } else {
	        // No results found, handle this case as needed (e.g., throw an exception or return null)
	    }
	}

    /**
     * Writes data to Elasticsearch.
     */
    protected function writeToElasticsearch()
    {
    	if (!$this->_elasticsearchClient) {
			return;
		}
        $params = [
            'index' => $this->_dataObject->config('name'),
            'id'    => $this->id(),
            'body'  => $this->data(),
        ];

        $this->_elasticsearchClient->index($params);
    }

    /**
     * Deletes data from Elasticsearch.
     */
    protected function deleteFromElasticsearch($id)
    {
    	if (!$this->_elasticsearchClient) {
			return;
		}
		
        $params = [
            'index' => $this->_dataObject->config('name'),
            'id'    => $id,
        ];

        $this->_elasticsearchClient->delete($params);
    }

	/**
	 * Specify data which should be serialized to JSON
	 *
	 * @return array Data of this object to be serialized
	 */
	public function jsonSerialize() : mixed {
	    return $this->data();
	}

	/**
	 * Create a clone of this object
	 *
	 * The dataObject property is also cloned to ensure a complete and independent copy.
	 */
	public function __clone()
	{
		$this->_dataObject = clone $this->_dataObject;
	}

}