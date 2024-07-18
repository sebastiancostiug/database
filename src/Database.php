<?php
/**
 * @package     Database
 *
 * @subpackage  Database
 *
 * @author      Sebastian Costiug <sebastian@overbyte.dev>
 * @copyright   2019-2023 Sebastian Costiug
 * @license     https://opensource.org/licenses/BSD-3-Clause
 *
 * @category    database
 *
 * @since       2023-10-27
 */

namespace database;

use PDO;
use common\Singleton;

/**
 * Database class
 */
class Database
{
    use Singleton;

    /**
     * @var string $_database
     */
    private static string $_database = '';

    /**
     * @var \PDO $connection
     */
    protected static ?\PDO $connection;

    /**
     * @var string $logFile Log file
     */
    protected static string $logFile;

    // /**
    //  * @var Memcached $cache Cache
    //  */
    // protected static ?\Memcached $cache;

    /**
     * The Database class provides a PDO connection to the database.
     * It reads the database configuration from the config file and creates a PDO instance.
     * It supports MySQL and SQLite drivers.
     *
     * @param array|null $credentials The database credentials.
     *
     * @return mixed
     * @throws DatabaseException if the connection fails or the driver is not supported.
     */
    final protected function __construct($credentials = [])
    {
        $databaseInfo = !empty($credentials) ? $credentials : parse_ini_file(base_path('.env'));

        self::$_database = $databaseInfo['DB_DATABASE'] ?? 'database';

        $driver    = $databaseInfo['DB_CONNECTION'] ?? 'mysql';
        $host      = $databaseInfo['DB_HOST'] ?? 'localhost';
        $port      = $databaseInfo['DB_PORT'] ?? '3306';
        $charset   = $databaseInfo['DB_ENCODING'] ?? 'utf8mb4';
        $collation = $databaseInfo['DB_COLLATION'] ?? 'utf8mb4_unicode_ci';
        $username  = $databaseInfo['DB_USERNAME'] ?? 'slim_base';
        $password  = $databaseInfo['DB_PASSWORD'] ?? 'password';

        switch ($driver) {
            case 'mysql':
                $dsn = "$driver:host=$host;dbname=" . self::$_database . ";port=$port;charset=$charset";
                break;

            case 'sqlite':
                $dsn = $driver . ':' . self::$_database;
                break;

            default:
                throw new DatabaseException("Unsupported database driver: $driver");
        }

        try {
            self::$connection = new \PDO($dsn, $username, $password);
            // self::$connection->exec("SET NAMES '$charset' COLLATE '$collation'");
            self::$connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$connection->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Connection failed: ' . $e->getMessage(),
                [
                    'params' => $databaseInfo,
                    'errorInfo' => $e->errorInfo ?? null,
                ]
            );
        }
    }

    /**
     * Closes the database connection when the object is destroyed.
     *
     * @return void
     */
    public function __destruct()
    {
        self::$connection = null;
    }

    /**
     * Check if the database is connected.
     *
     * @return boolean Returns true if the database is connected, false otherwise.
     */
    public static function connected(): bool
    {
        if (!self::$connection) {
            return false;
        }

        try {
            self::$connection->query('SELECT 1');
        } catch (\PDOException $e) {
            return false;
        }

        return true;
    }

    /**
     * tableExists()
     *
     * @param string $table The table
     *
     * @return boolean
     * @throws DatabaseException if the connection or the query fails.
     */
    public static function tableExists($table = null): bool
    {
        throw_when(is_null($table), ['Table name is required', func_get_args()], DatabaseException::class);
        throw_when(!self::connected(), 'Database is not connected', DatabaseException::class);

        // $cacheKey = "table_exists_$table";
        // $cachedResult = self::$cache->get($cacheKey);
        // if ($cachedResult !== false) {
        //     return $cachedResult;
        // }

        $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE `table_schema` = :database AND `table_name` = :table;';
        $params = [
            'database' => self::$_database,
            'table'    => $table,
        ];

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($params);

            $result = $statement->fetch(PDO::FETCH_COLUMN);

            $exists = !empty($result);

            // self::$cache->set($cacheKey, $exists, 3600); // cache for 1 hour

            return $exists;
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Table exists failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null,
                ]
            );
        }
    }

    /**
     * tableIsEmpty()
     *
     * @param string $table The table
     *
     * @return boolean
     * @throws DatabaseException if the $table is empty or the query fails.
     */
    public static function tableIsEmpty($table = null): bool
    {
        throw_when(is_null($table), ['Table name is required', func_get_args()], DatabaseException::class);
        throw_when(!self::connected(), 'Database connection failed', DatabaseException::class);

        // $cacheKey = "table_is_empty_$table";
        // $cachedResult = self::$cache->get($cacheKey);
        // if ($cachedResult !== false) {
        //     return $cachedResult;
        // }

        try {
            $result = self::count($table) === 0;
             // self::$cache->set($cacheKey, $result, 3600); // cache for 1 hour

            return $result;
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Table is empty failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null,
                ]
            );
        }
    }

    /**
     * execute custom query
     *
     * @param string     $query  The query
     * @param array|null $params The query parameters
     *
     * Example:
     * $query = "SELECT * FROM `user` WHERE `name` = :name AND `age` > :age";
     * $params = [
     *     'name'  => 'John Doe',
     *     'age'   => 25,
     * ];
     *
     * $users = $db->query($query, $params);
     *
     * @return array|boolean For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries will return an array of rows as associative arrays. For other successful queries will return TRUE. FALSE on failure.
     * @throws DatabaseException if the query is empty or it fails.
     */
    public static function query(string $query = '', array $params = null): array|bool
    {
        throw_when(empty($query), ['Query is required', func_get_args()], DatabaseException::class);

        try {
            $statement = self::$connection->prepare($query);

            $command = strtolower(strtok($query, ' '));

            if (in_array($command, ['select', 'show', 'describe', 'explain'])) {
                $statement->execute($params);

                return $statement->fetchAll(PDO::FETCH_ASSOC);
            }

            return $statement->execute($params);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Query failed: ' . $th->getMessage(),
                [
                    'params'    => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null,
                    'query'     => $query,
                ],
            );
        }
    }

    /**
     * select one row from a table
     *
     * @param string $table  The table
     * @param string $column The column name
     * @param mixed  $value  The column value
     *
     *  Example:
     *  $entries = $db->row('user', 'name', 'John Doe');
     *
     * @return mixed The first row of the result set.
     * @throws DatabaseException if the table, column or value is empty or the query fails.
     */
    public static function row(string $table = null, string $column = null, mixed $value = null): mixed
    {
        throw_when(is_null($table) || is_null($column) || is_null($value), ['All parameters are required', func_get_args()], DatabaseException::class);

        $sql = "SELECT * FROM `$table` WHERE `$column` = :value";

        $params = ['value' => $value,];

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($params);

            return $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Row failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * select one column from a table
     *
     * @param string $table  The table
     * @param string $column The column name
     *
     * Example:
     * $entries = $db->column('user', 'name');
     *
     * @return array|boolean An array containing all of the result.
     */
    public static function column(string $table = null, string $column = null): array|bool
    {
        throw_when(is_null($table) || is_null($column), ['All parameters ar required' . func_get_args()], DatabaseException::class);

        $sql = "SELECT `{$column}` FROM `{$table}`";

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute();

            return $statement->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Column failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * select one value from a table
     *
     * @param string $table    The table
     * @param string $interest The column value we need
     * @param string $column   The column name
     * @param mixed  $value    The column value
     *
     *    Example:
     *    $value = $db->value('name', 'user', 'id', 1);
     *
     * @return mixed The first column of the result set.
     * @throws DatabaseException if the table, interest, column or value is empty or the query fails.
     */
    public static function value(string $table = null, string $interest = null, string $column = null, mixed $value = null): mixed
    {
        throw_when(
            is_null($table) || is_null($interest) || is_null($column) || is_null($value),
            [
                'All parameters are required',
                func_get_args()
            ],
            DatabaseException::class
        );

        $sql = "SELECT `{$interest}` FROM `{$table}` WHERE `{$column}` = :value";

        $params = ['value' => $value];

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($params);

            return $statement->fetch(PDO::FETCH_COLUMN);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Value failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * count entries
     *
     * @param string $table The table
     *
     * Example:
     * $userCount = $db->count('user');
     *
     * @return mixed The number of rows in the result set.
     * @throws DatabaseException if the table is empty or the query fails.
     */
    public static function count($table = null): mixed
    {
        throw_when(is_null($table), ['Table name is required', func_get_args()], DatabaseException::class);

        $sql = "SELECT COUNT(`id`) FROM `{$table}`";

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute();

            return $statement->fetch(PDO::FETCH_COLUMN);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Count failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * insert()
     *
     * @param string $table The table
     * @param array  $data  Data to be inserted
     *
     * Example:
     * $insert = $db->insert('user', [
     *      'name'  => 'John Doe',
     *      'email' => 'johndoe@example.com',
     * ]);
     *
     * @return integer
     * @throws DatabaseException if the table or data is empty or the query fails.
     */
    public static function insert(string $table = null, array $data = []): int
    {
        throw_when(
            is_null($table) || empty($data),
            [
            'All parameters are required',
            func_get_args()
            ],
            DatabaseException::class
        );

        if (!self::tableExists($table)) {
            self::createTable($table, $data);
        }

        $keys = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `$table` (`$keys`) VALUES ($placeholders)";

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute(array_values($data));

            return self::lastInsertId();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Insert failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * Batch insert data into a database table.
     *
     * @param string $table The name of the table to insert data into.
     * @param array  $data  An array of data to be inserted into the table.
     *
     * @return integer The number of rows affected by the insert operation.
     */
    public static function batchInsert(string $table = null, array $data = []): int
    {
        throw_when(
            is_null($table) || empty($data),
            [
            'All parameters are required',
            func_get_args()
            ],
            DatabaseException::class
        );

        if (!self::tableExists($table)) {
            self::createTable($table, $data);
        }

        $keys = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `$table` (`$keys`) VALUES ";

        $values = [];
        $rowCount = count($data[array_key_first($data)]);
        for ($i = 0; $i < $rowCount; $i++) {
            $sql .= "($placeholders),";
            foreach ($data as $column) {
                $values[] = $column[$i];
            }
        }
        $sql = rtrim($sql, ',');

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($values);

            return self::lastInsertId();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Batch insert failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * update()
     *
     * @param string $table       The table
     * @param string $column      The column name to specify row to update
     * @param mixed  $columnValue The column value to specify row to update
     * @param array  $data        Data to be inserted
     *
     * Example:
     * $insert = $db->update('user', 'id', 1,[
     *      'name'  => 'John Doe',
     *      'email' => 'johndoe@example.com',
     * ]);
     *
     * @return boolean
     * @throws DatabaseException if the table, column, columnValue or data is empty or the query fails.
     */
    public static function update(string $table = null, string $column = null, mixed $columnValue = null, array $data = []): bool
    {
        throw_when(
            is_null($table)|| is_null($column) || is_null($columnValue) || empty($data),
            [
                'All parameters are required',
                func_get_args()
            ],
            DatabaseException::class
        );

        $placeholders = implode(', ', array_map(function ($key) {
            return "$key = :$key";
        }, array_keys($data)));

        $sql = "UPDATE `$table` SET $placeholders WHERE `$column` = :columnValue";
        $data['columnValue'] = $columnValue;

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute($data);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Update failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * Upserts data into the specified table.
     *
     * @param string $table The name of the table to upsert data into.
     * @param array  $data  The data to be upserted into the table.
     *
     * @return boolean Returns true if the upsert operation was successful, false otherwise.
     */
    public static function upsert(string $table = null, array $data = []): bool
    {
        throw_when(
            is_null($table) || empty($data),
            [
                'All parameters are required',
                func_get_args()
            ],
            DatabaseException::class
        );

        $keys = implode('`, `', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `$table` (`$keys`) VALUES ($placeholders) ON DUPLICATE KEY UPDATE ";

        $update = [];
        foreach ($data as $key => $value) {
            $update[] = "`$key` = VALUES(`$key`)";
        }
        $sql .= implode(', ', $update);

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute(array_values($data));
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Upsert failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * update()
     *
     * @param string $table  The table
     * @param string $column The column name to specify row to delete
     * @param mixed  $value  The column value to specify row to delete
     *
     *   Example:
     *   $insert = $db->delete('user', 'id', 1);
     *
     * @return boolean
     * @throws DatabaseException if the table, column or value is empty or the query fails.
     */
    public static function delete(string $table = null, string $column = null, mixed $value = null): bool
    {
        throw_when(is_null($table) || is_null($column) || is_null($value), ['All parameters are required', func_get_args()], DatabaseException::class);

        $sql = "DELETE FROM `{$table}` WHERE `{$column}` = :value";

        $params = ['value' => $value,];

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($params);

            return true;
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Delete failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * lastInsertId()
     *
     * @return integer
     * @throws DatabaseException if the query fails.
     */
    public static function lastInsertId(): int|false
    {
        try {
            return self::$connection->lastInsertId();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Last insert ID failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * createTable()
     *
     * @param string $table The table
     * @param array  $data  Data to be inserted
     *
     * @return boolean
     * @throws DatabaseException if the table or data is empty or the query fails.
     */
    public static function createTable($table = null, array $data = []): bool
    {
        throw_when(is_null($table) || empty($data), ['Table name and data are required', func_get_args()], DatabaseException::class);

        $columnDefinitions = implode(', ', array_map(function ($key, $value) {
            if (!in_array($key, ['id', 'created_at', 'updated_at'])) {
                return "`$key` " . self::getDataType($value);
            }
        }, array_keys($data), $data));

        $columnDefinitions = trim($columnDefinitions, ', ');

        $sql = "CREATE TABLE IF NOT EXISTS `$table` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY, $columnDefinitions, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP)";

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Create table failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * dropTable()
     *
     * @param string $table The table
     *
     * @return boolean TRUE on success or FALSE on failure.
     * @throws DatabaseException if the table is empty or the query fails.
     */
    public static function dropTable($table = null): bool
    {
        throw_when(is_null($table), ['Table name is required', func_get_args()], DatabaseException::class);

        $sql = "DROP TABLE IF EXISTS `{$table}`";

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Drop table failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * truncateTable()
     *
     * @param string $table The table
     *
     * @return boolean
     * @throws DatabaseException if the table is empty or the query fails.
     */
    public static function truncateTable($table = null): bool
    {
        throw_when(is_null($table), ['Table name is required', func_get_args()], DatabaseException::class);

        $sql = "TRUNCATE TABLE `{$table}`";

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Truncate table failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * Retrieves the column names of a specified table in the database.
     *
     * @param string|null $table The name of the table. If null, retrieves column names for all tables.
     *
     * @return array|boolean An array of column names.
     */
    public static function getColumns($table = null): array|bool
    {
        throw_when(is_null($table), ['Table name is required', func_get_args()], DatabaseException::class);

        $sql = "SHOW COLUMNS FROM `{$table}`";

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute();

            return $statement->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Get column names failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * columnExists()
     *
     * @param string $table  The table
     * @param string $column The column name
     *
     * @return boolean TRUE if the column exists, FALSE if it does not.
     */
    public static function columnExists($table = null, $column = null): bool
    {
        throw_when(is_null($table) || is_null($column), ['Table and column names are required', func_get_args()], DatabaseException::class);

        $sql = 'SELECT COUNT(*) FROM information_schema.columns WHERE `table_schema` = :database AND `table_name` = :table AND `column_name` = :column;';

        $params['database'] = self::$_database;
        $params['table']    = $table;
        $params['column']   = $column;

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($params);

            $result = $statement->fetch(PDO::FETCH_COLUMN);

            return !empty($result);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Column exists failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * createColumn()
     *
     * @param string $table The table
     * @param string $name  The column name
     * @param mixed  $value The column value
     *
     * @return boolean
     */
    public static function createColumn($table = null, $name = null, mixed $value = null): bool
    {
        throw_when(is_null($table) || is_null($name), ['Table and column names are required', func_get_args()], DatabaseException::class);

        if (self::columnExists($table, $name)) {
            return true;
        }

        $sql = "ALTER TABLE `{$table}` ADD `{$name}` " . self::getDataType($value);

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Create column failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * deleteColumn()
     *
     * @param string $table The table
     * @param string $name  The column name
     *
     * @return boolean TRUE on success or FALSE on failure.
     * @throws DatabaseException if the table or column is empty or the query fails.
     */
    public static function deleteColumn($table, $name): bool
    {
        throw_when(is_null($table) || is_null($name), ['Table and column names are required', func_get_args()], DatabaseException::class);

        $sql = "ALTER TABLE `{$table}` DROP `{$name}`";

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Delete column failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * modifyColumn()
     *
     * @param string $table The table
     * @param string $name  The column name
     * @param mixed  $value The column value
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public static function modifyColumn($table, $name, mixed $value): bool
    {
        $sql = "ALTER TABLE `{$table}` MODIFY `{$name}` " . self::getDataType($value);

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Modify column failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * renameColumn()
     *
     * @param string $table    The table
     * @param string $old_name The old column name
     * @param string $new_name The new column name
     *
     * @return boolean
     * @throws DatabaseException if the table, old_name or new_name is empty or the query fails.
     */
    public static function renameColumn($table, $old_name, $new_name): bool
    {
        throw_when(is_null($table) || is_null($old_name) || is_null($new_name), ['Table and column names are required', func_get_args()], DatabaseException::class);

        $sql = "ALTER TABLE `{$table}` CHANGE `{$old_name}` `{$new_name}` " . self::getColumnDataType($table, $old_name);

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Rename column failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * getColumnDataType
     *
     * @param string $table The table
     * @param string $name  The column name
     *
     * @return mixed
     * @throws DatabaseException if the table or name is empty or the query fails.
     */
    public static function getColumnDataType($table, $name): mixed
    {
        throw_when(is_null($table) || is_null($name), ['Table and column names are required', func_get_args()], DatabaseException::class);

        $sql = 'SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :table AND COLUMN_NAME = :name';

        $params = [
            'table' => $table,
            'name'  => $name,
        ];

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($params);

            return $statement->fetch(PDO::FETCH_COLUMN);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Get column data type failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * indexExists()
     *
     * @param string $table The table
     * @param string $name  The index name
     *
     * @return boolean
     * @throws DatabaseException if the table or name is empty or the query fails.
     */
    public static function indexExists($table = null, $name = null): bool
    {
        throw_when(is_null($table) || is_null($name), ['Table and index names are required', func_get_args()], DatabaseException::class);

        $sql = 'SELECT COUNT(*) FROM information_schema.statistics WHERE `table_schema` = :database AND `table_name` = :table AND `index_name` = :name;';

        $params['database'] = self::$_database;
        $params['table']    = $table;
        $params['name']     = $name;

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($params);

            $result = $statement->fetch(PDO::FETCH_COLUMN);

            return !empty($result);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Index exists failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * createIndex
     *
     * @param string $table The table
     * @param string $name  The index name
     * @param string $type  The index type
     * @param string $value The index value
     *
     * @return boolean
     */
    public static function createIndex($table, $name, $type, $value): bool
    {
        throw_when(is_null($table) || is_null($name) || is_null($type) || is_null($value), ['All parameters are required', func_get_args()], DatabaseException::class);

        if (self::indexExists($table, $name)) {
            return true;
        }

        $sql = "ALTER TABLE `{$table}` ADD {$type} `{$name}` (`{$value}`)";

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Create index failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * deleteIndex
     *
     * @param string $table The table
     * @param string $name  The index name
     *
     * @return boolean TRUE on success or FALSE on failure.
     * @throws DatabaseException if the table or name is empty or the query fails.
     */
    public static function deleteIndex($table, $name): bool
    {
        throw_when(is_null($table) || is_null($name), ['Table and index names are required', func_get_args()], DatabaseException::class);

        $sql = "ALTER TABLE `{$table}` DROP INDEX `{$name}`";

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Delete index failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * foreignKeyExists()
     *
     * @param string $table The table
     * @param string $name  The foreign key name
     *
     * @return boolean
     * @throws DatabaseException if the table or name is empty or the query fails.
     */
    public static function foreignKeyExists($table = null, $name = null): bool
    {
        throw_when(is_null($table) || is_null($name), ['Table and foreign key names are required', func_get_args()], DatabaseException::class);

        $sql = 'SELECT COUNT(*) FROM information_schema.table_constraints WHERE `table_schema` = :database AND `table_name` = :table AND `constraint_name` = :name AND `constraint_type` = \'FOREIGN KEY\';';

        $params['database'] = self::$_database;
        $params['table']    = $table;
        $params['name']     = $name;

        try {
            $statement = self::$connection->prepare($sql);
            $statement->execute($params);

            $result = $statement->fetch(PDO::FETCH_COLUMN);

            return !empty($result);
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Foreign key exists failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * createForeignKey
     *
     * @param string $table        The table
     * @param string $foreignTable The foreign table
     * @param string $name         The foreign key name
     * @param string $value        The foreign key value
     * @param string $onDelete     The on delete action
     * @param string $onUpdate     The on update action
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public static function createForeignKey($table, $foreignTable, $name, $value, $onDelete = 'NO ACTION', $onUpdate = 'NO ACTION'): bool
    {
        throw_when(is_null($table) || is_null($foreignTable) || is_null($name) || is_null($value), ['All parameters are required', func_get_args()], DatabaseException::class);

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$value}`) REFERENCES `{$foreignTable}` (`id`) ON DELETE `{$onDelete}` ON UPDATE `{$onUpdate}`";

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Create foreign key failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * deleteForeignKey
     *
     * @param string $table The table
     * @param string $name  The foreign key name
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public static function deleteForeignKey($table, $name): bool
    {
        throw_when(is_null($table) || is_null($name), ['Table and foreign key names are required', func_get_args()], DatabaseException::class);

        $sql = "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`";

        try {
            $statement = self::$connection->prepare($sql);

            return $statement->execute();
        } catch (\Throwable $th) {
            throw new DatabaseException(
                'Delete foreign key failed: ' . $th->getMessage(),
                [
                    'params' => func_get_args(),
                    'errorInfo' => $th->errorInfo ?? null
                ]
            );
        }
    }

    /**
     * getDataType()
     *
     * @param mixed $value The value
     *
     * @return string
     */
    protected static function getDataType(mixed $value)
    {
        switch (gettype($value)) {
            case 'integer':
                return 'INT';
            case 'double':
                return 'DOUBLE';
            case 'string':
                return 'VARCHAR(255)';
            case 'boolean':
                return 'BOOLEAN';
            case 'NULL':
                return 'NULL';
            default:
                return 'VARCHAR(255)';
        }
    }
}
