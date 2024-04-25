<?php
/**
 * @package     slim-base
 *
 * @subpackage  Query
 *
 * @author      Sebastian Costiug <sebastian@overbyte.dev>
 * @copyright   2019-2024 Sebastian Costiug
 * @license     https://opensource.org/licenses/BSD-3-Clause
 *
 * @category    slim-base
 *
 * @since       2024-04-23
 */

namespace database;

use common\Collection;

/**
 * Query class
 */
class Query
{
    /**
     * @var mixed $db The database connection object.
     */
    protected $db;

    /**
     * @var string $sql The SQL query string.
     */
    protected $sql;

    /**
     * @var array $params The parameters to bind to the query.
     */
    protected $params = [];

    /**
     * The selected columns for the query.
     *
     * @var array
     */
    protected $select = [];

    /**
     * The table name or alias from which to select data.
     *
     * @var string
     */
    protected $from;

    /**
     * @var mixed $where The WHERE clause of the query.
     */
    protected $where;

    /**
     * @var mixed $orWhere The OR WHERE clause of the query.
     */
    protected $orWhere;

    /**
     * @var string|null The ORDER BY clause of the query.
     */
    protected $orderBy;

    /**
     * @var string|null $groupBy The GROUP BY clause of the query.
     */
    protected $groupBy;

    /**
     * @var mixed $having The HAVING clause of the query.
     */
    protected $having;

    /**
     * @var mixed $orHaving The OR HAVING clause of the query.
     */
    protected $orHaving;

    /**
     * @var boolean $distinct Whether the query should return distinct results.
     */
    protected $distinct;

    /**
     * @var integer|null The limit for the query.
     */
    protected $limit;

    /**
     * @var integer|null The offset for the query.
     */
    protected $offset;

    /**
     * @var mixed $join The join clause for the query.
     */
    protected $join;

    /**
     * The type of join used in the query.
     *
     * @var string
     */
    protected $joinType;

    /**
     * The relationships to be eager loaded on the query.
     *
     * @var array
     */
    protected $with;

    /**
     * The via property represents the connection via which the query is executed.
     *
     * @var string
     */
    protected $via;

    /**
     * Query constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Adds parameters to the query.
     *
     * @param array $params The parameters to add to the query.
     *
     * @return $this The current Query instance.
     */
    public function addParams(array $params): self
    {
        if (!empty($params)) {
            if (empty($this->params)) {
                $this->params = $params;
            } else {
                foreach ($params as $name => $value) {
                    if (is_int($name)) {
                        $this->params[] = $value;
                    } else {
                        $this->params[$name] = $value;
                    }
                }
            }
        }

        return $this;
    }

/**
 * Normalizes the SELECT columns passed to select().
 *
 * @param string|array $columns The columns to select.
 *
 * @return array
 */
    protected function normalizeColumns($columns): array
    {
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        } elseif (is_array($columns)) {
            $columns = new Collection($columns);
            $columns = $columns->trim()->flatten();
        } else {
            throw new DatabaseException('Invalid SELECT clause format.');
        }

        return $columns;
    }

    /**
     * Insert new rows into the specified table.
     *
     * @param string $table   The name of the table.
     * @param array  $columns An array of column names.
     * @param array  $values  The values to insert, as an array of arrays, where each sub-array represents a row.
     *
     * @return $this The current Query instance.
     */
    public function insert($table, array $columns, array $values): self
    {
        $this->params = [];
        $valuesPlaceholder = [];

        foreach ($values as $value) {
            $valuesPlaceholder[] = '(' . implode(', ', array_fill(0, count($value), '?')) . ')';
            $this->params = array_merge($this->params, $value);
        }

        $this->sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $valuesPlaceholder);

        return $this;
    }

    /**
     * Updates rows in the specified table.
     *
     * @param string $table   The name of the table.
     * @param array  $columns The columns to update.
     * @param array  $values  The values to update.
     *
     * @return $this The current Query instance.
     */
    public function update($table, array $columns, array $values): self
    {
        $this->params = [];
        $set = [];

        foreach ($columns as $column) {
            $set[] = $column . ' = ?';
        }

        $this->params = array_merge($this->params, $values);

        $this->sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $set);

        return $this;
    }

    /**
     * Saves data to the specified table.
     *
     * @param string  $table     The name of the table to save data to.
     * @param array   $columns   An array of column names.
     * @param array   $values    An array of values to be saved.
     * @param boolean $newRecord Optional. Indicates whether this is a new record. Default is false.
     *
     * @return self Returns an instance of the Query class.
     */
    public function save($table, array $columns, array $values, $newRecord = false): self
    {
        if ($newRecord) {
            return $this->insert($table, $columns, $values);
        }

        return $this->update($table, $columns, $values);
    }

    /**
     * Deletes records from the database.
     *
     * @return $this The current Query instance.
     */
    public function delete(): self
    {
        $this->sql = 'DELETE FROM ';

        return $this;
    }

    /**
     * Selects columns from the database table.
     *
     * @param array|string $columns The columns to select.
     *
     * @return $this The current Query instance.
     */
    public function select($columns = []): self
    {
        $this->select = $this->normalizeColumns($columns);

        return $this;
    }

    /**
     * Set the table to perform the query on.
     *
     * @param string $table The name of the table.
     *
     * @return $this The current Query instance.
     */
    public function from($table): self
    {
        $this->from = $table;

        return $this;
    }

    /**
     * Sets the WHERE clause for the query.
     *
     * @param array|string $conditions The conditions for the WHERE clause.
     *
     * @return $this The current Query instance.
     */
    public function where($conditions): self
    {
        if (is_string($conditions)) {
            $this->where[] = $conditions;
        } elseif (is_array($conditions)) {
            foreach ($conditions as $key => $value) {
                if (is_string($key)) {
                    // Handle associative array conditions
                    $this->where[] = "`$key` = :$key";
                    $this->params[":$key"] = $value;
                } elseif (is_array($value) && count($value) === 3) {
                    // Handle array conditions with three elements
                    [$operator, $column, $value] = $value;
                    $this->where[] = "`$column` $operator :$column";
                    $this->params[":$column"] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Adds an "AND" condition to the query.
     *
     * @param array|string $conditions The conditions to add.
     *
     * @return self The updated Query object.
     */
    public function andWhere($conditions): self
    {
        if (is_string($conditions)) {
            $this->where[] = $conditions;
        } elseif (is_array($conditions)) {
            foreach ($conditions as $key => $value) {
                if (is_string($key)) {
                    // Handle associative array conditions
                    $this->where[] = "`$key` = :$key";
                    $this->params[":$key"] = $value;
                } elseif (is_array($value) && count($value) === 3) {
                    // Handle array conditions with three elements
                    [$operator, $column, $value] = $value;
                    $this->where[] = "`$column` $operator :$column";
                    $this->params[":$column"] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Adds an OR condition to the query.
     *
     * @param array|string $conditions The conditions to be added.
     *
     * @return self The updated Query object.
     */
    public function orWhere($conditions): self
    {
        if (is_string($conditions)) {
            $this->orWhere[] = $conditions;
        } elseif (is_array($conditions)) {
            foreach ($conditions as $key => $value) {
                if (is_string($key)) {
                    // Handle associative array conditions
                    $this->orWhere[] = "`$key` = :$key";
                    $this->params[":$key"] = $value;
                } elseif (is_array($value) && count($value) === 3) {
                    // Handle array conditions with three elements
                    [$operator, $column, $value] = $value;
                    $this->orWhere[] = "`$column` $operator :$column";
                    $this->params[":$column"] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Filters the given condition.
     *
     * @param mixed $condition The condition to filter.
     *
     * @return array The filtered condition.
     */
    private function filterCondition(mixed$condition): mixed
    {
        if (!is_array($condition)) {
            return $condition;
        }

        if (!isset($condition[0])) {
            foreach ($condition as $name => $value) {
                if ($this->isEmpty($value)) {
                    unset($condition[$name]);
                }
            }

            return $condition;
        }

        $operator = array_shift($condition);

        switch (strtoupper($operator)) {
            case 'NOT':
            case 'AND':
            case 'OR':
                foreach ($condition as $i => $operand) {
                    $subCondition = $this->filterCondition($operand);
                    if ($this->isEmpty($subCondition)) {
                        unset($condition[$i]);
                    } else {
                        $condition[$i] = $subCondition;
                    }
                }

                if (empty($condition)) {
                    return [];
                }
                break;
            case 'BETWEEN':
            case 'NOT BETWEEN':
                if (array_key_exists(1, $condition) && array_key_exists(2, $condition)) {
                    if ($this->isEmpty($condition[1]) || $this->isEmpty($condition[2])) {
                        return [];
                    }
                }
                break;
            default:
                if (array_key_exists(1, $condition) && $this->isEmpty($condition[1])) {
                    return [];
                }
        }

        array_unshift($condition, $operator);

        return $condition;
    }

    /**
     * Checks if a value is empty.
     *
     * @param mixed $value The value to check.
     *
     * @return boolean Returns true if the value is empty, false otherwise.
     */
    private function isEmpty(mixed $value)
    {
        return $value === '' || $value === [] || $value === null || is_string($value) && trim($value) === '';
    }

    /**
     * Filters the query results based on the given conditions.
     *
     * @param array|string $conditions The conditions to filter the results.
     *
     * @return $this The current Query instance.
     */
    public function filterWhere($conditions): self
    {
        $conditions = $this->filterCondition($conditions);
        if ($conditions !== []) {
            $this->andWhere($conditions);
        }

        return $this;
    }

    /**
     * Adds a "WHERE" condition to the query using the "AND" operator.
     *
     * @param array|string $conditions The conditions to be added to the query.
     *
     * @return $this The current Query instance.
     */
    public function andFilterWhere($conditions): self
    {
        $conditions = $this->filterCondition($conditions);
        if ($conditions !== []) {
            $this->andWhere($conditions);
        }

        return $this;
    }

    /**
     * Adds a "OR" condition to the query's WHERE clause using the provided conditions.
     *
     * @param array|string $conditions The conditions to be added to the query.
     *
     * @return $this The current Query instance.
     */
    public function orFilterWhere($conditions): self
    {
        $conditions = $this->filterCondition($conditions);
        if ($conditions !== []) {
            $this->orWhere($conditions);
        }

        return $this;
    }

    /**
     * Adds a HAVING clause to the query.
     *
     * @param array|string $conditions The conditions for the HAVING clause.
     *
     * @return self
     */
    public function having($conditions): self
    {
        if (is_string($conditions)) {
            $this->having[] = $conditions;
        } elseif (is_array($conditions)) {
            foreach ($conditions as $condition) {
                if (is_string($condition)) {
                    $this->having[] = $condition;
                } elseif (is_array($condition) && count($condition) === 3) {
                    [$operator, $column, $value] = $condition;
                    $this->having[] = "`$column` $operator :$column";
                    $this->params[":$column"] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Adds an "AND HAVING" condition to the query.
     *
     * @param array|string $conditions The conditions to add.
     *
     * @return self The updated Query object.
     */
    public function andHaving($conditions): self
    {
        if (is_string($conditions)) {
            $this->having[] = $conditions;
        } elseif (is_array($conditions)) {
            foreach ($conditions as $condition) {
                if (is_string($condition)) {
                    $this->having[] = $condition;
                } elseif (is_array($condition) && count($condition) === 3) {
                    [$operator, $column, $value] = $condition;
                    $this->having[] = "`$column` $operator :$column";
                    $this->params[":$column"] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Adds an "OR HAVING" condition to the query.
     *
     * @param array|string $conditions The conditions to add.
     *
     * @return self The updated Query object.
     */
    public function orHaving($conditions): self
    {
        if (is_string($conditions)) {
            $this->orHaving[] = $conditions;
        } elseif (is_array($conditions)) {
            foreach ($conditions as $condition) {
                if (is_string($condition)) {
                    $this->orHaving[] = $condition;
                } elseif (is_array($condition) && count($condition) === 3) {
                    [$operator, $column, $value] = $condition;
                    $this->orHaving[] = "`$column` $operator :$column";
                    $this->params[":$column"] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Set the distinct flag for the query.
     *
     * @param boolean $value Whether to enable or disable distinct.
     *
     * @return $this The current Query instance.
     */
    public function distinct($value = true): self
    {
        $this->distinct = $value;

        return $this;
    }

    /**
     * Set the maximum number of rows to be returned by the query.
     *
     * @param integer $limit The maximum number of rows to be returned.
     *
     * @return $this The current Query instance.
     */
    public function limit($limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set the offset for the query.
     *
     * @param integer $offset The offset value.
     *
     * @return $this The current Query instance.
     */
    public function offset($offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Sets the columns to order the query results by.
     *
     * @param array|string $columns The columns to order by.
     *
     * @return $this The current Query instance.
     */
    public function orderBy($columns): self
    {
        $this->orderBy = $columns;

        return $this;
    }

    /**
     * Sets the GROUP BY clause of the query.
     *
     * @param array|string $columns The columns to group by.
     *
     * @return $this The current Query instance.
     */
    public function groupBy($columns): self
    {
        $this->groupBy = $columns;

        return $this;
    }

    /**
     * Joins a table to the query.
     *
     * @param string $table      The name of the table to join.
     * @param array  $conditions The join conditions.
     * @param string $type       The type of join (optional).
     *
     * @return $this The current Query instance.
     */
    public function join($table, array $conditions, $type = ''): self
    {
        $this->join = [$table, $conditions];
        $this->joinType = $type;

        return $this;
    }

    /**
     * Set the join type for the query.
     *
     * @param string $type The join type.
     *
     * @return $this The current Query instance.
     */
    public function joinType($type): self
    {
        $this->joinType = $type;

        return $this;
    }

    /**
     * Set the relations to be eager loaded on the query.
     *
     * @param mixed $relations The relations to be eager loaded.
     *
     * @return $this The current Query instance.
     */
    public function with(mixed $relations): self
    {
        $this->with = $relations;

        return $this;
    }

    /**
     * Sets the "via" parameter for the query.
     *
     * @param mixed $via The value to set as the "via" parameter.
     *
     * @return $this The current Query instance.
     */
    public function via(mixed $via): self
    {
        $this->via = $via;

        return $this;
    }

    /**
     * Get the SQL query string.
     *
     * @return string The SQL query string.
     */
    public function getSql()
    {
        $this->prepare();

        return $this->sql;
    }

    /**
     * Prepares the query for execution.
     *
     * @return void
     */
    private function prepare()
    {
        if ($this->sql !== null) {
            switch (strtoupper(substr($this->sql, 0, 6))) {
                case 'INSERT':
                    return;
                case 'UPDATE':
                    if ($this->where !== null) {
                        $this->sql .= ' WHERE ' . $this->where;
                    }
                    return;
                case 'DELETE':
                    $this->sql .= $this->from;
                    if ($this->where !== null) {
                        $this->sql .= ' WHERE ' . $this->where;
                    }
                    return;
            }
        }

        $this->sql = 'SELECT ';

        if ($this->distinct) {
            $this->sql .= 'DISTINCT ';
        }

        if (empty($this->select)) {
            $this->sql .= '*';
        } else {
            $this->sql .= implode(', ', $this->select);
        }

        $this->sql .= ' FROM ' . $this->from;

        if ($this->join !== null) {
            $this->sql .= ' ' . $this->joinType . ' JOIN ' . $this->join[0] . ' ON ' . $this->join[1];
        }

        if ($this->where !== null) {
            $this->sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if ($this->orWhere !== null) {
            $this->sql .= ' OR WHERE ' . implode(' OR ', $this->orWhere);
        }

        if ($this->groupBy !== null) {
            $this->sql .= ' GROUP BY ' . $this->groupBy;
        }

        if ($this->having !== null) {
            $this->sql .= ' HAVING ' . implode(' AND ', $this->having);
        }

        if ($this->orHaving !== null) {
            $this->sql .= ' OR HAVING ' . implode(' OR ', $this->orHaving);
        }

        if ($this->orderBy !== null) {
            $this->sql .= ' ORDER BY ' . $this->orderBy;
        }

        if ($this->limit !== null) {
            $this->sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $this->sql .= ' OFFSET ' . $this->offset;
        }

        return;
    }

    /**
     * Executes the query.
     *
     * @return mixed The query results.
     */
    public function execute()
    {
        $this->prepare();

        return $this->db->query($this->sql, $this->params);
    }

    /**
     * Retrieves a single row from the database.
     *
     * @return mixed The fetched row from the database.
     */
    public function one()
    {
        $this->limit(1);
        $results = $this->execute();

        return $results[0] ?? null;
    }

    /**
     * Retrieve all records from the database.
     *
     * @return array The array of records retrieved from the database.
     */
    public function all()
    {
        return $this->execute();
    }

    /**
     * Get the value of the query.
     *
     * @return mixed The value of the query.
     */
    public function selected()
    {
        $this->limit(1);
        $results = $this->execute();

        if ($results && is_array($results[0])) {
            // Get the column names from the select method
            $columns = $this->select();

            // Initialize an array to store the values
            $values = [];

            // Loop through the column names and retrieve the corresponding values
            foreach ($columns as $column) {
                $values[$column] = $results[0][$column] ?? null;
            }

            return (count($values) > 1) ? $values : reset($values);
        }

        return null;
    }

    /**
     * Get the count of the query results.
     *
     * @return integer The count of the query results.
     */
    public function count()
    {
        $this->select('COUNT(*) as count');
        $result = $this->execute();

        return $result[0]['count'];
    }

    /**
     * Get the maximum value of a column.
     *
     * @param string $column The column name.
     * @return mixed The maximum value of the column.
     */
    public function max($column)
    {
        $this->select("MAX($column) as max");
        $result = $this->execute();

        return $result[0]['max'];
    }

    /**
     * Get the minimum value of a column from the query result.
     *
     * @param string $column The column to retrieve the minimum value from.
     * @return mixed The minimum value of the specified column.
     */
    public function min($column)
    {
        $this->select("MIN($column) as min");
        $result = $this->execute();

        return $result[0]['min'];
    }

    /**
     * Calculates the sum of a specified column in the query result.
     *
     * @param string $column The name of the column to calculate the sum for.
     * @return float|null The sum of the specified column, or null if no result is found.
     */
    public function sum($column)
    {
        $this->select("SUM($column) as sum");
        $result = $this->execute();

        return $result[0]['sum'];
    }

    /**
     * Calculate the average value of a given column.
     *
     * @param string $column The name of the column to calculate the average on.
     * @return float The average value of the column.
     */
    public function avg($column)
    {
        $this->select("AVG($column) as avg");
        $result = $this->execute();

        return $result[0]['avg'];
    }

    /**
     * Executes the query and returns the last inserted ID.
     *
     * @return integer The last inserted ID.
     */
    public function lastInsertId()
    {
        return $this->db->lastInsertId();
    }
}
