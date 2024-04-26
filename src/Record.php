<?php
/**
 * @package     Database
 *
 * @subpackage  Record
 *
 * @author      Sebastian Costiug <sebastian@overbyte.dev>
 * @copyright   2019-2023 Sebastian Costiug
 * @license     https://opensource.org/licenses/BSD-3-Clause
 *
 * @category    Database
 *
 * @since       2023.10.15
 */

namespace database;

/**
 * Record trait for database records.
 *
 * This trait assumes that the class using it has the following properties:
 * - $attributes: An array of key-value pairs representing the attributes of the record.
 *
 * This trait also assumes that the class using it is extended from the Eventful class
 * because it uses the following methods defined in the Eventful class:
 * - notify
 *
 * it can be to get the columns of the table, set the attributes of the record, and perform aggregate functions on the query results.
 */
trait Record
{
    // Event names
    const EVENT_BEFORE_INSERT = 'beforeInsert';
    const EVENT_AFTER_INSERT  = 'afterInsert';
    const EVENT_BEFORE_UPDATE = 'beforeUpdate';
    const EVENT_AFTER_UPDATE  = 'afterUpdate';
    const EVENT_BEFORE_DELETE = 'beforeDelete';
    const EVENT_AFTER_DELETE  = 'afterDelete';

    /**
     * Checks if this record is eventful.
     *
     * @return boolean Returns true if the record is eventful, false otherwise.
     */
    public function isEventful()
    {
        return $this instanceof \core\components\Eventful;
    }

    /**
     * Get the model's table name
     *
     * @return string
     */
    public static function getTable()
    {
        return strtolower(class_basename(static::class));
    }

    /**
     * tableExists
     *
     * @return boolean
     */
    public static function tableExists()
    {
        return app()->db()->instance->tableExists(static::getTable());
    }

    /**
     * Check if the model is a new record
     *
     * @return boolean
     */
    public function isNewRecord()
    {
        return empty($this->id);
    }

    /**
     * This method is called before inserting a record.
     * Override this method to add custom logic before saving.
     *
     * @return void
     */
    public function beforeSave()
    {
        if ($this->isNewRecord() && $this->isEventful()) {
            $this->notify(self::EVENT_BEFORE_INSERT);
        } else {
            if (method_exists($this, 'setChangedAttributes')) {
                $this->setChangedAttributes();
            }
            if ($this->isEventful()) {
                $this->notify(self::EVENT_BEFORE_UPDATE);
            }
        }
    }

    /**
     * This method is called after inserting a record.
     * Override this method to add custom logic after saving.
     *
     * @return void
     */
    public function afterSave()
    {
        if ($this->isEventful()) {
            if ($this->isNewRecord()) {
                $this->notify(self::EVENT_AFTER_INSERT);
            } else {
                $this->notify(self::EVENT_AFTER_UPDATE);
            }
        }
    }

    /**
     * beforeDelete
     *
     * @return void
     */
    public function beforeDelete()
    {
        if ($this->isEventful()) {
            $this->notify(self::EVENT_BEFORE_DELETE);
        }
    }

    /**
     * afterDelete
     *
     * @return void
     */
    public function afterDelete()
    {
        if ($this->isEventful()) {
            $this->notify(self::EVENT_AFTER_DELETE);
        }
    }

    /**
     * Find a record by conditions
     *
     * @return Query The model instance if found, or false if not found.
     */
    public static function find(): Query
    {
        return app()->db()->select()->from(static::getTable());
    }

    /**
     * Find a record by a specific field and value
     *
     * @param  array $conditions Conditions
     *
     * @return array|false The model instance if found, or false if not found.
     */
    public static function findBy(array $conditions): array
    {
        return static::find()->where($conditions)->one();
    }

    /**
     * Returns a single model instance
     *
     * @param  integer $id ID
     *
     * @return array|false The model instance if found, or false if not found.
     */
    public static function findOne($id): array
    {
        return  static::findBy(['id' => $id]);
    }

    /**
     * Find all records by a specific field and value
     *
     * @param  array $conditions Conditions
     *
     * @return array|false An array with all records of the model or false if no records are found.
     */
    public static function findAllBy(array $conditions): array
    {
        return static::find()->where($conditions)->all();
    }

    /**
     * Get all records of the model
     *
     * @return array|false An array with all records of the model or false if no records are found.
     */
    public static function findAll(): array
    {
        return static::find()->all();
    }

    /**
     * Retrieves database records based on the provided filter.
     *
     * @param array   $filter  The filter to apply to the records.
     * @param integer $offset  The offset to start from.
     * @param integer $count   The number of records to retrieve.
     * @param string  $orderBy The column to order by  (default is 'id').
     * @param string  $sort    The order to use (default is 'ASC').
     *
     * @return Query
     */
    public static function fetchFromDatabase(array $filter = [], $offset = null, $count = null, $orderBy = 'id', $sort = 'ASC'): Query
    {
        $records = app()->db()->select()->from(static::getTable());

        if (!empty($filter)) {
            foreach ($filter as $type => $conditions) {
                $records = $records->andWhere($conditions, $type);
            }
        }

        $records = $records->orderBy($orderBy, $sort);

        if ($count) {
            $records = $records->limit($count);
        }

        if ($offset) {
            $records = $records->offset($offset);
        }

        return $records;
    }

    /**
     * Save the record
     *
     * @return integer|boolean The ID of the inserted record or boolean on update and failure.
     */
    public function save(): int|bool
    {
        if (!$this->validate()) {
            return false;
        }
        $this->beforeSave();

        if ($this->isNewRecord() && method_exists($this, 'getAttributes')) {
            $attributes = array_filter($this->getAttributes(), function ($value) {
                return !empty($value);
            });
        } elseif (method_exists($this, 'getChangedAttributes')) {
            $attributes = $this->getChangedAttributes() + ['id' => $this->id];
        }

        if (!empty($attributes)) {
            $result = app()->db()
                ->save($this->table, array_keys($this->getLabels()), $attributes, $this->isNewRecord())
                ->execute();

            $this->afterSave();
        }

        return $result ?? $this->id ?? true;
    }

    /**
     * Delete the record
     *
     * @return boolean True if the record is successfully deleted, false otherwise.
     */
    public function delete(): bool
    {
        $this->beforeDelete();

        if (!app()->db()->delete()->from($this->table)->where(['id' => $this->id])->execute()) {
            return false;
        }

        $this->afterDelete();

        return true;
    }

    /**
     * Get a record relation using hasOne relationship
     *
     * @param  string      $class    Class
     * @param  string|null $viaTable Intermediate table
     *
     * @return Query.
     */
    public function hasOne($class, $viaTable = null): Query
    {
        $foreignKey = strtolower(class_basename($this)) . '_id';
        $query = app()->db()
            ->select()
            ->from($class::getTable())
            ->where([$foreignKey => $this->id]);

        if ($viaTable) {
            $query->join($viaTable, [$foreignKey => $this->id]);
        }

        return $query;
    }

    /**
     * Get record relation using hasMany relationship
     *
     * @param  string      $class    Class
     * @param  string|null $viaTable Intermediate table
     *
     * @return Query.
     */
    public function hasMany($class, $viaTable = null): Query
    {
        $foreignKey = strtolower(class_basename($this)) . '_id';
        $query = app()->db()
            ->select()
            ->from($class::getTable())
            ->where([$foreignKey => $this->id]);

        if ($viaTable) {
            $query->join($viaTable, [$foreignKey => $this->id]);
        }

        return $query;
    }

    /**
     * Sets the attributes of the record with the given key-value pairs.
     *
     * @param array $attributes The key-value pairs to set as attributes.
     *
     * @return self Returns the current instance of the Record class.
     */
    public function setAttributes(array $attributes): self
    {
        $columns = $this->getColumns();

        foreach ($attributes as $key => $value) {
            if (in_array($key, $columns)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * getColumns
     *
     * @return array
     */
    public static function getColumns()
    {
        return app()->db()->instance->getColumns(static::getTable());
    }

    /**
     * Check if a record exists in the database based on a column and value.
     *
     * @param string $column The column to search in.
     * @param mixed  $value  The value to search for.
     *
     * @return boolean Returns true if a record exists, false otherwise.
     */
    public static function exists($column, mixed $value)
    {
        return (bool) app()->db()->select()->from(static::getTable())->where([$column => $value])->one();
    }

    /**
     * count the records
     *
     * @return integer
     */
    public static function count(): int
    {
        return app()->db()->count(static::getTable());
    }

    /**
     * Get the maximum value of a column.
     *
     * @param string $column The column name.
     *
     * @return mixed The maximum value of the column.
     */
    public static function max($column): mixed
    {
        return app()->db()->max($column, static::getTable());
    }

    /**
     * Get the minimum value of a column from the query result.
     *
     * @param string $column The column to retrieve the minimum value from.
     *
     * @return mixed The minimum value of the specified column.
     */
    public static function min($column): mixed
    {
        return app()->db()->min($column, static::getTable());
    }

    /**
     * Calculates the sum of a specified column in the query result.
     *
     * @param string $column The name of the column to calculate the sum for.
     *
     * @return float|null The sum of the specified column, or null if no result is found.
     */
    public static function sum($column): float|null
    {
        return app()->db()->sum($column, static::getTable());
    }

    /**
     * Calculate the average value of a given column.
     *
     * @param string $column The name of the column to calculate the average on.
     *
     * @return float The average value of the column.
     */
    public static function avg($column): float
    {
        return app()->db()->avg($column, static::getTable());
    }

    /**
     * Executes the query and returns the last inserted ID.
     *
     * @return integer The last inserted ID.
     */
    public static function lastInsertId()
    {
        return app()->db()->instance->lastInsertId();
    }
}
