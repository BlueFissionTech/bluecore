<?php

namespace BlueFission\BlueCore\Model;

use BlueFission\Collections\Group;

class SQLiteModelStore
{
    protected $_config = [
        'location' => '',
        'name' => '',
        'fields' => [],
        'key' => 'id',
        'column_types' => [],
    ];

    protected $_data = [];
    protected $_rows = [];
    protected $_query = '';
    protected $_status = null;
    protected $_conditions = [];
    protected $_order = [];

    public function __construct(array $config = [])
    {
        $this->config($config);

        foreach ((array)$this->config('fields') as $field) {
            $this->_data[$field] = null;
        }
    }

    public function activate()
    {
        return $this;
    }

    public function config($config = null, $value = null)
    {
        if ($config === null) {
            return $this->_config;
        }

        if (is_array($config)) {
            foreach ($config as $key => $item) {
                if (array_key_exists($key, $this->_config)) {
                    $this->_config[$key] = $item;
                }
            }

            return $this;
        }

        if (func_num_args() === 1) {
            return $this->_config[$config] ?? null;
        }

        if (array_key_exists($config, $this->_config)) {
            $this->_config[$config] = $value;
        }

        return $this;
    }

    public function field(string $field, $value = null): mixed
    {
        if (func_num_args() > 1) {
            $this->_data[$field] = $value;

            return $this;
        }

        return $this->_data[$field] ?? null;
    }

    public function data(): array
    {
        return $this->_data;
    }

    public function clear()
    {
        foreach (array_keys($this->_data) as $field) {
            $this->_data[$field] = null;
        }

        return $this;
    }

    public function condition($field, $condition = '', $value = '')
    {
        $condition = $condition ?: '=';
        $this->_conditions[$field] = $condition;

        if (func_num_args() > 2) {
            $this->_data[$field] = $value;
        }

        return $this;
    }

    public function order($field, $direction = 'ASC')
    {
        $this->_order[$field] = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return $this;
    }

    public function write()
    {
        $db = $this->open();
        $this->ensureSchema($db);

        $key = $this->primary();
        $identifier = $key ? ($this->_data[$key] ?? null) : null;
        $record = $this->writableData();

        if ($identifier !== null && $this->exists($db, $identifier)) {
            $assignments = [];
            foreach (array_keys($record) as $field) {
                if ($field === $key) {
                    continue;
                }
                $assignments[] = sprintf('`%s` = :%s', $field, $field);
            }

            $this->_query = sprintf(
                'UPDATE `%s` SET %s WHERE `%s` = :__key',
                $this->config('name'),
                implode(', ', $assignments),
                $key
            );

            $statement = $db->prepare($this->_query);
            foreach ($record as $field => $value) {
                if ($field === $key) {
                    continue;
                }
                $statement->bindValue(':' . $field, $value, $this->sqliteType($value));
            }
            $statement->bindValue(':__key', $identifier, SQLITE3_INTEGER);
            $result = $statement->execute();
        } else {
            $columns = [];
            $placeholders = [];

            foreach ($record as $field => $value) {
                if ($field === $key && $value === null) {
                    continue;
                }

                $columns[] = sprintf('`%s`', $field);
                $placeholders[] = ':' . $field;
            }

            $this->_query = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $this->config('name'),
                implode(', ', $columns),
                implode(', ', $placeholders)
            );

            $statement = $db->prepare($this->_query);
            foreach ($record as $field => $value) {
                if ($field === $key && $value === null) {
                    continue;
                }
                $statement->bindValue(':' . $field, $value, $this->sqliteType($value));
            }
            $result = $statement->execute();

            if ($result && $key && $identifier === null) {
                $this->_data[$key] = $db->lastInsertRowID();
            }
        }

        $this->_status = $result ? 'Success.' : 'Failed.';
        if ($result instanceof \SQLite3Result) {
            $result->finalize();
        }

        $db->close();

        return $this;
    }

    public function read()
    {
        $db = $this->open();
        $this->ensureSchema($db);

        $where = [];
        foreach ($this->_data as $field => $value) {
            if ($value === null) {
                continue;
            }

            $operator = $this->_conditions[$field] ?? '=';
            $where[] = sprintf('`%s` %s :%s', $field, $operator, $field);
        }

        $this->_query = sprintf('SELECT * FROM `%s`', $this->config('name'));
        if ($where) {
            $this->_query .= ' WHERE ' . implode(' AND ', $where);
        }
        if ($this->_order) {
            $sort = [];
            foreach ($this->_order as $field => $direction) {
                $sort[] = sprintf('`%s` %s', $field, $direction);
            }
            $this->_query .= ' ORDER BY ' . implode(', ', $sort);
        }

        $statement = $db->prepare($this->_query);
        foreach ($this->_data as $field => $value) {
            if ($value === null) {
                continue;
            }
            $statement->bindValue(':' . $field, $value, $this->sqliteType($value));
        }

        $result = $statement->execute();
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }

        $this->_rows = $rows;
        $this->_status = 'Success.';

        if ($rows) {
            foreach ($rows[0] as $field => $value) {
                $this->_data[$field] = $value;
            }
        }

        $result->finalize();
        $db->close();

        return $this;
    }

    public function delete()
    {
        $db = $this->open();
        $key = $this->primary();
        $identifier = $key ? ($this->_data[$key] ?? null) : null;

        if ($key && $identifier !== null) {
            $this->_query = sprintf('DELETE FROM `%s` WHERE `%s` = :__key', $this->config('name'), $key);
            $statement = $db->prepare($this->_query);
            $statement->bindValue(':__key', $identifier, SQLITE3_INTEGER);
            $result = $statement->execute();
            $this->_status = $result ? 'Success.' : 'Failed.';
            if ($result instanceof \SQLite3Result) {
                $result->finalize();
            }
        }

        $db->close();

        return $this;
    }

    public function result()
    {
        return new Group($this->_rows);
    }

    public function contents(): mixed
    {
        return $this->_rows ?: $this->_data;
    }

    public function query(): string
    {
        return $this->_query;
    }

    public function status($message = null): mixed
    {
        if (func_num_args() === 0) {
            return $this->_status;
        }

        $this->_status = $message;

        return $this;
    }

    public function primary(): string
    {
        return $this->config('key');
    }

    protected function open(): \SQLite3
    {
        $database = $this->config('location') ?: ':memory:';

        return new \SQLite3($database);
    }

    protected function ensureSchema(\SQLite3 $db): void
    {
        $columns = [];
        $key = $this->primary();
        $types = (array)$this->config('column_types');

        foreach ((array)$this->config('fields') as $field) {
            $type = $types[$field] ?? null;
            if (!$type) {
                $type = $field === $key
                    ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
                    : (in_array($field, ['created', 'updated', 'date'], true) ? 'DATETIME' : 'TEXT');
            }
            $columns[] = sprintf('`%s` %s', $field, $type);
        }

        $query = sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (%s)',
            $this->config('name'),
            implode(', ', $columns)
        );

        $this->_query = $query;
        $db->exec($query);
    }

    protected function exists(\SQLite3 $db, $identifier): bool
    {
        $query = sprintf('SELECT 1 FROM `%s` WHERE `%s` = :__key LIMIT 1', $this->config('name'), $this->primary());
        $statement = $db->prepare($query);
        $statement->bindValue(':__key', $identifier, SQLITE3_INTEGER);
        $result = $statement->execute();
        $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;
        if ($result instanceof \SQLite3Result) {
            $result->finalize();
        }

        return (bool)$row;
    }

    protected function writableData(): array
    {
        $record = [];

        foreach ((array)$this->config('fields') as $field) {
            $record[$field] = $this->_data[$field] ?? null;
        }

        return $record;
    }

    protected function sqliteType($value): int
    {
        if (is_int($value)) {
            return SQLITE3_INTEGER;
        }

        if (is_float($value)) {
            return SQLITE3_FLOAT;
        }

        if ($value === null) {
            return SQLITE3_NULL;
        }

        return SQLITE3_TEXT;
    }
}
