<?php

namespace BlueFission\BlueCore\Model;

use BlueFission\Arr;
use BlueFission\Obj;
use BlueFission\Str;
use BlueFission\Val;

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
        if (Val::isNotEmpty($this->_key)) {
            return $this->_key;
        }

        foreach ($this->_fields as $field) {
            if (Str::endsWith($field, 'id', Str::IGNORE_CASE)) {
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
            !Arr::has($this->_fields, 'created', true) &&
            !$this->_save_related_tables &&
            (
                !$this->_dataObject->field($id) ||
                (
                    Val::isNotNull($values) &&
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

    private function removeField(string $field): void
    {
        $this->_fields = Arr::make($this->_fields)
            ->filter(static fn ($value) => $value !== $field)
            ->toArray();
    }
}
