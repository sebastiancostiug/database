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

use database\exceptions\DatabaseRecordException;

/**
 * Record class
 */
class Record
{
    /**
     * @var Database $database Database
     */
    protected Database $database;

    /**
     * @var string $table Table
     */
    protected static string $table;

    /**
     * @var object $statement Statement
     */
    protected ?object $statement;

    /**
     * @var string $sql SQL
     */
    protected string $sql = '';

    /**
     * @var array $params Params
     */
    protected array $params = [];

    /**
     * @var array $attributes Attributes
     */
    protected array $attributes = [];

    /**
     * @var array $relation Relation
     */
    protected array $relation = [];

    /**
     * @var string $logFile Log file
     */
    protected string $logFile;

    /**
     * __construct
     *
     * @param string $class Class
     *
     * @return void
     * -- commented @throws DatabaseRecordException If the table does not exist.
     */
    public function __construct($class = null)
    {
        $this->database = Database::getInstance();

        self::$table = strtolower(class_basename($class));

        // throw_when(!$this->database->tableExists(static::$table), [static::$table . ' table does not exist'], DatabaseRecordException::class);
    }

    /**
     * tableExists
     *
     * @return boolean
     */
    public function tableExists(): bool
    {
        return $this->database->tableExists(static::$table);
    }

    /**
     * columnExists
     *
     * @param string $column Column
     *
     * @return boolean
     */
    public function columnExists($column): bool
    {
        return $this->database->columnExists(static::$table, $column);
    }

    /**
     * exists
     *
     * @param string $column Column
     * @param string $value  Value
     *
     * @return boolean
     */
    public function exists($column, $value): bool
    {
        return (bool) $this->find()->where([$column => $value])->one();
    }

    /**
     * Sets the SQL query to retrieve all records from the table.
     *
     * @return self
     */
    public function find(): self
    {
        $this->sql = 'SELECT * FROM `' . static::$table . '`';

        return $this;
    }

    /**
     * Adds a WHERE clause to the SQL query based on the given conditions.
     *
     * @param array $conditions An associative array of column-value pairs to use in the WHERE clause.
     *
     * @return $this The current instance of the Record object.
     */
    public function where(array $conditions): self
    {
        $this->addConditions(' WHERE ', $conditions, ' AND ');

        return $this;
    }

    /**
     * Adds an "AND" clause to the WHERE statement of the query.
     *
     * @param array $conditions An associative array of conditions to add to the WHERE statement.
     *
     * @return $this The current instance of the Record object.
     */
    public function andWhere(array $conditions): self
    {
        $this->addConditions(' AND ', $conditions, ' AND ');

        return $this;
    }

    /**
     * Adds an OR WHERE clause to the query.
     *
     * @param array $conditions An associative array of conditions to add to the query.
     *
     * @return $this
     */
    public function orWhere(array $conditions): self
    {
        $this->addConditions(' OR ', $conditions, ' OR ');

        return $this;
    }

    /**
     * Adds a "LIKE" condition to the query.
     *
     * @param string $field The field to search.
     * @param string $value The value to search for.
     *
     * @return $this
     */
    public function like($field, $value): self
    {
        $this->sql .= ' WHERE `' . $field . '` LIKE :' . $field;
        $this->params[$field] = $value;

        return $this;
    }

    /**
     * Adds a "NOT LIKE" condition to the query.
     *
     * @param string $field The field to search.
     * @param string $value The value to search for.
     *
     * @return $this
     */
    public function notLike($field, $value): self
    {
        $this->sql .= ' WHERE `' . $field . '` NOT LIKE :' . $field;
        $this->params[$field] = $value;

        return $this;
    }

    /**
     * Adds a "BETWEEN" condition to the query.
     *
     * @param string $field The field to search.
     * @param array  $value The value to search for.
     *
     * @return $this
     */
    public function between($field, array $value): self
    {
        $this->sql .= ' WHERE `' . $field . '` BETWEEN :' . $field . '1 AND :' . $field . '2';
        $this->params[$field . '1'] = $value[0];
        $this->params[$field . '2'] = $value[1];

        return $this;
    }

    /**
     * Adds a "NOT BETWEEN" condition to the query.
     *
     * @param string $field The field to search.
     * @param array  $value The value to search for.
     *
     * @return $this
     */
    public function notBetween($field, array $value): self
    {
        $this->sql .= ' WHERE `' . $field . '` NOT BETWEEN :' . $field . '1 AND :' . $field . '2';
        $this->params[$field . '1'] = $value[0];
        $this->params[$field . '2'] = $value[1];

        return $this;
    }

    /**
     * Adds a "IN" condition to the query.
     *
     * @param string $field The field to search.
     * @param string $value The value to search for.
     *
     * @return $this
     */
    public function in($field, $value): self
    {
        $this->sql .= ' WHERE `' . $field . '` IN (:' . $field . ')';
        $this->params[$field] = $value;

        return $this;
    }

    /**
     * Adds a "NOT IN" condition to the query.
     *
     * @param string $field The field to search.
     * @param string $value The value to search for.
     *
     * @return $this
     */
    public function notIn($field, $value): self
    {
        $this->sql .= ' WHERE `' . $field . '` NOT IN (:' . $field . ')';
        $this->params[$field] = $value;

        return $this;
    }

    /**
     * Adds a "IS NULL" condition to the query.
     *
     * @param string $field The field to search.
     *
     * @return $this
     */
    public function isNull($field): self
    {
        $this->sql .= ' WHERE `' . $field . '` IS NULL';

        return $this;
    }

    /**
     * Adds a "IS NOT NULL" condition to the query.
     *
     * @param string $field The field to search.
     *
     * @return $this
     */
    public function isNotNull($field): self
    {
        $this->sql .= ' WHERE `' . $field . '` IS NOT NULL';

        return $this;
    }


    /**
     * Adds conditions to the SQL query.
     *
     * @param string $prefix     The prefix to add before the conditions (e.g. "WHERE").
     * @param array  $conditions An associative array of conditions to add to the query.
     * @param string $suffix     The suffix to add after each condition (e.g. "=").
     *
     * @return void
     */
    private function addConditions($prefix, array $conditions, $suffix): void
    {
        if (strpos($this->sql, ' WHERE ') === false) {
            $prefix = ' WHERE ';
        }

        $conditionsStr = '';
        foreach ($conditions as $key => $value) {
            $conditionsStr .= '`' . $key . '`' . ' = :' . $key . $suffix;
            $this->params[$key] = $value;
        }
        $conditionsStr = rtrim($conditionsStr, $suffix);

        $this->sql .= $prefix . $conditionsStr;
    }

    /**
     * Adds an ORDER BY clause to the SQL query.
     *
     * @param string $field The name of the field to order by.
     *
     * @return self Returns the current instance of the Record class.
     */
    public function orderBy($field): self
    {
        $this->sql = $this->sql . ' ORDER BY ' . $field;

        return $this;
    }

    /**
     * Sets the limit for the SQL query.
     *
     * @param integer $limit The maximum number of rows to return.
     *
     * @return self Returns the Record instance for method chaining.
     */
    public function limit($limit): self
    {
        $this->sql = $this->sql . ' LIMIT ' . $limit;

        return $this;
    }

    /**
     * has relation
     *
     * @param string $foreignTable Foreign table
     *
     * @return self
     */
    public function has($foreignTable): self
    {
        $this->relation = [
            'localTable'   => static::$table,
            'foreignTable' => strtolower(class_basename($foreignTable)),
        ];
        extract($this->relation);

        $this->sql = "SELECT * FROM `{$foreignTable}` WHERE `{$foreignTable} `.`id`' = `{$localTable}`.`{$foreignTable}_id`";

        return $this;
    }

    /**
     * via relation
     *
     * @param string $intermediaryTable Intermediary table
     *
     * @return self
     */
    public function via($intermediaryTable): self
    {
        extract($this->relation);

        $this->sql = "SELECT FROM `{$foreignTable}` " .
            "INNER JOIN `{$intermediaryTable}` " .
            "ON `{$foreignTable}`.`id` = `{$intermediaryTable}`.`{$foreignTable}_id` " .
            "WHERE `{$intermediaryTable}`.`{$localTable}_id` = `{$localTable}`.`id`";

        return $this;
    }

    /**
     * Returns a single record from the database based on the current query.
     *
     * @return array|boolean Returns an array containing the record data or false if no record is found.
     */
    public function one(): array|bool
    {
        return $this->database->one($this->sql, $this->params);
    }

    /**
     * Returns all records from the database table.
     *
     * @return array|boolean An array of records or false if there was an error.
     */
    public function all(): array|bool
    {
        return $this->database->all($this->sql, $this->params);
    }

    /**
     * Saves the record to the database.
     *
     * If the record is new, it will be inserted into the database. Otherwise, it will be updated.
     *
     * @return integer|boolean Returns the ID of the record if it was successfully saved, false otherwise.
     */
    public function save(): int|bool
    {
        if ($this->isNewRecord()) {
            return $this->database->insert(static::$table, $this->attributes);
        } else {
            return $this->database->update(static::$table, 'id', $this->attributes['id'], $this->attributes);
        }
    }

    /**
     * Deletes the current record from the database.
     *
     * @return boolean True if the record was successfully deleted, false otherwise.
     */
    public function delete(): bool
    {
        return $this->database->delete(static::$table, 'id', $this->attributes['id']);
    }

    /**
     * Check if the record is new.
     *
     * @return boolean Returns true if the record is new, false otherwise.
     */
    public function isNewRecord(): bool
    {
        return !isset($this->attributes['id']);
    }

    /**
     * lastInsertId
     *
     * @return integer
     */
    public function lastInsertId()
    {
        return $this->database->lastInsertId();
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
     * getSql
     *
     * @return string
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * getColumns
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->database->getColumns(static::$table);
    }
}
