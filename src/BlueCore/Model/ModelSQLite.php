<?php

namespace BlueFission\BlueCore\Model;

use BlueFission\Arr;
use BlueFission\Obj;

class ModelSQLite extends BaseModel
{
    protected $_database = '';
    protected $_table = '';
    protected $_fields = [];
    protected $_columnTypes = [];
    protected $_ignore_null = true;
    protected $_save_related_tables = false;
    protected $_key = '';

    public function __construct($database = null)
    {
        if ($database !== null) {
            $this->_database = $database;
        }

        $this->_type = get_class($this);
        $this->_dataObject = new SQLiteModelStore([
            'location' => $this->_database,
            'name' => $this->_table,
            'fields' => $this->_fields,
            'key' => $this->resolvePrimaryKey(),
            'column_types' => $this->_columnTypes,
        ]);
        $this->_dataObject->activate();
        $this->init();
        $this->_idField = $this->_dataObject->primary();
        $this->clear();
    }

    protected function init()
    {
    }

    protected function resolvePrimaryKey(): string
    {
        if ($this->_key) {
            return $this->_key;
        }

        foreach ($this->_fields as $field) {
            if (strtolower(substr($field, -2)) === 'id') {
                return $field;
            }
        }

        return $this->_idField;
    }

    public function field(string $field, $value = null): mixed
    {
        if (!Arr::hasKey($this->_dataObject->data(), $field) && !Arr::has($this->_fields, $field)) {
            return null;
        }

        if (func_num_args() > 1) {
            return $this->_dataObject->field($field, $value);
        }

        return $this->_dataObject->field($field);
    }

    public function write($values = null): Obj
    {
        $forceCreatedTimestamp = false;
        $forceUpdatedTimestamp = false;
        $id = $this->_idField;

        if (
            !in_array('created', $this->_fields, true) &&
            !$this->_save_related_tables &&
            (
                !$this->_dataObject->field($id) ||
                (
                    isset($values) &&
                    !(is_array($values) && isset($values[$id])) &&
                    !(is_object($values) && isset($values->$id))
                )
            )
        ) {
            $this->_fields[] = 'created';
            $forceCreatedTimestamp = true;
        }

        if (!in_array('updated', $this->_fields, true) && !$this->_save_related_tables) {
            $this->_fields[] = 'updated';
            $forceUpdatedTimestamp = true;
        }

        $this->_dataObject->config('fields', $this->_fields);
        $result = parent::write($values);

        if ($forceCreatedTimestamp) {
            $key = array_search('created', $this->_fields, true);
            if ($key !== false) {
                unset($this->_fields[$key]);
            }
        }

        if ($forceUpdatedTimestamp) {
            $key = array_search('updated', $this->_fields, true);
            if ($key !== false) {
                unset($this->_fields[$key]);
            }
        }

        $this->_fields = array_values($this->_fields);
        $this->_dataObject->config('fields', $this->_fields);

        return $result;
    }

    public function condition($field, $condition = '', $value = '')
    {
        $this->_dataObject->condition($field, $condition, $value);

        return $this;
    }

    public function result()
    {
        return $this->_dataObject->result();
    }

    public function query()
    {
        return $this->_dataObject->query();
    }
}
