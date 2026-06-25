<?php
namespace BlueFission\BlueCore\Model;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Val;
use BlueFission\Behavioral\Behaviors\Event;
use BlueFission\BlueCore\Model\BaseModel;
use BlueFission\Data\Storage\MySQLBulk;
use BlueFission\Connections\Database\MySQLLink;

/**
 * Class ModelSql
 *
 * This class extends the BaseModel class to create a Model for working with SQL databases.
 * It provides a convenient way to interact with SQL databases by wrapping the MySQLBulk data storage object.
 *
 * @package BlueFission\BlueCore\Model
 */
class ModelSql extends BaseModel {

	/**
	 * The name of the database table associated with the model
	 *
	 * @var string
	 */
	protected $_table = '';

	/**
	 * The fields in the database table associated with the model
	 *
	 * @var array
	 */
	protected $_fields = [];

	/**
	 * Whether to automatically join related tables when performing a query
	 *
	 * @var bool
	 */
	protected $_autojoin = true;

	/**
	 * Whether to ignore null values when performing a write operation
	 *
	 * @var bool
	 */
	protected $_ignore_null = true;

	/**
	 * Whether to save related tables when performing a write operation
	 *
	 * @var bool
	 */
	protected $_save_related_tables = false;

	/**
	 * ModelSql constructor.
	 *
	 * @param MySQLLink|null $link The database link to use for the model.
	 */
	public function __construct( MySQLLink $link = null )
	{
		if (Val::isNotNull($link)) {
			$link->when(Event::ACTION_FAILED, function($event) {
				// Handle the event when the link fails
				throw new \Exception("Database connection failed: " . $event->context->info);
			});
			$link->when(Event::FAILURE, function($event) {
				// Handle the event when the link fails
				throw new \Exception("Database connection failed: " . $event->context->info);
			});
			$link->open();
		}

		$this->_type = get_class($this);
		$this->_dataObject = new MySQLBulk([
			'location'=>null,
			'name'=>$this->_table,
			'fields'=>$this->_fields,
			'auto_join'=>$this->_autojoin,
			'ignore_null'=>$this->_ignore_null,
			'save_related_tables'=>$this->_save_related_tables,
		]);
		$this->_dataObject->activate();
		$this->init();
		$this->_idField = $this->_dataObject->primary();
		$this->clear();
	}

	/**
	 * Initializes the model.
	 * This function can be overridden in child classes to perform custom configurations.
	 */
	protected function init()
	{
		// With inheritance, configure the model here 
	}

	/**
	 * Gets or sets the value of a field in the model.
	 *
	 * @param string $field The name of the field to access.
	 * @param mixed|null $value The value to set for the field.
	 *
	 * @return mixed|null The value of the field, or null if the field does not exist.
	 */
	public function field(string $field, $value = null): mixed
	{
		if ( Arr::hasKey($this->_dataObject->_data, $field) || Arr::has($this->_fields, $field) ) {
			return parent::field($field, $value);
		}

		return null;
	}

	/**
	 * Writes the current record to the database.
	 *
	 * This method adds the "created" and "updated" fields to the current record
	 * if they do not already exist, then performs the actual write. The added
	 * fields are removed before returning.
	 *
	 * @return bool True on success, false on failure
	 */
	public function write($values = null) :Obj
	{
		$forceCreatedTimestamp = false;
		$forceUpdatedTimestamp = false;
		$id = $this->_idField;

		if (
			!Arr::has($this->_fields, 'created', true) &&
			!$this->_save_related_tables && 
			(!$this->_dataObject->$id ||
				(Val::isNotNull($values) &&
					!Arr::hasKey(Arr::toArray((array)$values, true), $id)
				)
			)
		) {
			$this->_fields[] = 'created';
			$forceCreatedTimestamp = true;
		}
		if (!Arr::has($this->_fields, 'updated', true) && !$this->_save_related_tables) {
			$this->_fields[] = 'updated';
			$forceUpdatedTimestamp = true;
		}
		$this->_dataObject->config('fields', $this->_fields);
		// Do the work
		$result = parent::write($values);
		if ($forceCreatedTimestamp) {
			$this->removeField('created');
		}
		if ($forceUpdatedTimestamp) {
			$this->removeField('updated');
		}
		$this->_dataObject->config('fields', $this->_fields);

		return $result;
	}

	/**
	 * Adds a condition to the current query.
	 *
	 * @param string $field The field to use in the condition
	 * @param string $condition The condition to use (e.g. "=", "<>", "LIKE")
	 * @param mixed $value The value to compare with
	 */
	public function condition( $field, $condition = '', $value = '')
	{
		$this->_dataObject->condition( $field, $condition, $value);
	}

	/**
	 * Returns the result of the current query.
	 *
	 * @return mixed The result of the query
	 */
	public function result()
	{
		return $this->_dataObject->result();
	}

	/**
	 * Returns the raw query string.
	 *
	 * @return string The raw query string
	 */
	public function query()
	{
		return $this->_dataObject->query();
	}

	private function removeField(string $field): void
	{
		$this->_fields = Arr::make($this->_fields)
			->filter(static fn ($value) => $value !== $field)
			->toArray();
	}

}
