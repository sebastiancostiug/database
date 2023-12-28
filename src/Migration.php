<?php
/**
 *
 * @package     Database
 *
 * @subpackage  Migration
 *
 * @author      Sebastian Costiug <sebastian@overbyte.dev>
 * @copyright   2019-2023 Sebastian Costiug
 * @license     https://opensource.org/licenses/BSD-3-Clause
 *
 * @category    database
 *
 * @since       2023-10-31
 *
 */

namespace seb\database;

/**
 * Migration class
 */
class Migration
{
    /**
     * @var Database $database Database
     */
    protected Database $database;

    /**
     * @var string $sql SQL
     */
    protected string $sql = '';

    /**
     * @var array $interactions Interactions with the database (tables, columns, indexes, foreign keys)
     */
    protected array $interactions = [];

    /**
     * @var string $caller Caller
     */
    protected string $caller = '';

    /**
     * __construct
     *
     * @return void
     */
    final public function __construct()
    {
        throw_when(php_sapi_name() !== 'cli', 'This function can only be run from the console.');

        $this->database = Database::getInstance();

        register_shutdown_function([&$this, 'shutdown']);
    }

    /**
     * shutdown
     *
     * @return void
     */
    public function shutdown(): void
    {
        if ($this->database->tableExists('migration_log') || $this->createMigrationLog()) {
            $this->run();
            if ($this->caller === 'up') {
                $this->addMigrationToLog();
            } elseif ($this->caller === 'down') {
                $this->removeMigrationFromLog();
            }
        }
    }

    /**
     * createMigrationLogTable
     *
     * @return boolean
     */
    private function createMigrationLog():bool
    {
        return $this->database->createTable('migration_log', ['name' => 'varchar(255) NOT NULL']) &&
        $this->database->createIndex('migration_log', 'idx_migration_log_unique_name', 'unique', 'name');
    }

    /**
     * addMigrationToLog
     *
     * @return void
     */
    private function addMigrationToLog(): void
    {
        $migrationName = class_basename(get_class($this));
        if (!$this->database->query('SELECT name FROM migration_log WHERE name = :name', ['name' => $migrationName])) {
            $this->database->query('INSERT INTO migration_log (name) VALUES (:name)', ['name' => $migrationName]);
        }
    }

    /**
     * deleteMigrationFromLog
     *
     * @return void
     */
    private function removeMigrationFromLog(): void
    {
        $migrationName = class_basename(get_class($this));
        if ($this->database->query('SELECT name FROM migration_log WHERE name = :name', ['name' => $migrationName])) {
            $this->database->query('DELETE FROM migration_log WHERE name = :name', ['name' => $migrationName]);
        }
    }

    /**
     * Sets the SQL query to create a new table if it does not exist.
     *
     * @param string $table The name of the table to be created.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function table($table): self
    {
        throw_when(empty($table), 'Table name is required');

        $this->caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]['function'];

        $this->interactions[$table] = [
            'columnsToAdd'      => [],
            'columnsToDrop'     => [],
            'indexesToAdd'      => [],
            'indexesToDrop'     => [],
            'foreignKeysToAdd'  => [],
            'foreignKeysToDrop' => [],
        ];

        return $this;
    }

    /**
     * addTable
     *
     * @param string $table The name of the table to be created.
     *
     * @return self
     */
    public function addTable($table): self
    {
        if (!empty($this->interactions[$table]['columnsToAdd'])) {
            $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (';

            foreach ($this->interactions[$table]['columnsToAdd'] as $name => $column) {
                $sql .= "`{$name}` {$column}, ";
            }

            $sql = rtrim($sql, ', ');

            $sql .= ') ENGINE=' . config('database.engine') . ' DEFAULT CHARSET=' . config('database.encoding') . ' COLLATE=' . config('database.collation');

            $this->database->query($sql);
        }

        return $this;
    }

    /**
     * alterTable
     *
     * @param string $table The name of the table to be altered.
     *
     * @return self
     */
    public function alterTable($table)
    {
        if (!empty($this->sql)) {
            $this->sql = 'ALTER TABLE `' . $table . '` ' . $this->sql;

            $this->database->query($this->sql);
            $this->sql = '';
        }

        return $this;
    }

    /**
     * Adds a new column to the table being migrated.
     *
     * @param string $name The name of the new column.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function addColumn(string $name): self
    {
        $table = array_key_last($this->interactions);

        $this->interactions[$table]['columnsToAdd'][$name] = '';

        return $this;
    }

    /**
     * addColumns
     *
     * @param string $table The name of the table to add the columns to.
     *
     * @return self
     */
    public function addColumns($table): self
    {
        foreach ($this->interactions[$table]['columnsToAdd'] as $name => $column) {
            if ($this->database->columnExists($table, $name)) {
                unset($this->interactions[$table]['columnsToAdd'][$name]);
            }
        }

        if (!empty($this->interactions[$table]['columnsToAdd'])) {
            foreach ($this->interactions[$table]['columnsToAdd'] as $name => $column) {
                $this->sql .= 'ADD COLUMN `' . $name . '` ' . $column . ', ';
            }
        }

        return $this;
    }

    /**
     * Add an index to a table.
     *
     * @param string $type    The type of the index.
     * @param array  $columns The columns to include in the index.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function addIndex($type, array $columns = []): self
    {
        $table = array_key_last($this->interactions);

        $name = "idx_{$table}_{$type}_" . implode('_', $columns);

        if ($this->database->indexExists($table, $name)) {
            return $this;
        }

        if (!empty($columns)) {
            $columns = '`' . implode('`, `', $columns) . '`';
        }

        $this->interactions[$table]['indexesToAdd'][] = "$type INDEX `{$name}` ({$columns})";

        return $this;
    }

    /**
     * addIndexes
     *
     * @param string $table The name of the table to add the indexes to.
     *
     * @return self
     */
    public function addIndexes($table): self
    {
        if (!empty($this->interactions[$table]['indexesToAdd'])) {
            $sql = 'ALTER TABLE ' . $table . ' ';

            foreach ($this->interactions[$table]['indexesToAdd'] as $index) {
                $sql .= 'ADD ' . $index . ', ';
            }

            $sql = rtrim($sql, ', ');
            $this->database->query($sql);
        }

        return $this;
    }

    /**
     * Adds a foreign key constraint to the table being migrated.
     *
     * @param string $column    The name of the column to add the foreign key constraint to.
     * @param string $refTable  The name of the table to reference.
     * @param string $refColumn The name of the column to reference.
     * @param string $onDelete  The action to perform when a referenced row is deleted.
     * @param string $onUpdate  The action to perform when a referenced row is updated.
     *
     * @return self Returns the Migration instance for method chaining.
     */
    public function addForeignKey($column, $refTable, $refColumn, $onDelete = 'CASCADE', $onUpdate = 'CASCADE'): self
    {
        $table = array_key_last($this->interactions);

        $name = "fk_{$table}_{$column}_{$refTable}_{$refColumn}";

        if ($this->database->foreignKeyExists($table, $name)) {
            return $this;
        }

        $this->interactions[$table]['foreignKeysToAdd'][] = "CONSTRAINT `{$name}` FOREIGN KEY (`$column`) REFERENCES `{$refTable}` (`$refColumn`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}";

        return $this;
    }

    /**
     * addForeignKeys
     *
     * @param string $table The name of the table to add the foreign keys to.
     *
     * @return self
     */
    public function addForeignKeys($table): self
    {
        if (!empty($this->interactions[$table]['foreignKeysToAdd'])) {
            $sql = 'ALTER TABLE ' . $table . ' ';

            foreach ($this->interactions[$table]['foreignKeysToAdd'] as $foreignKey) {
                $sql .= 'ADD ' . $foreignKey . ', ';
            }

            $sql = rtrim($sql, ', ');
            $this->database->query($sql);
        }

        return $this;
    }

    /**
     * Remove a column from the table.
     *
     * @param string $name The name of the column to remove.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function dropColumn($name): self
    {
        $table = array_key_last($this->interactions);

        $this->interactions[$table]['columnsToDrop'][] = $name;

        return $this;
    }

    /**
     * dropColumns
     *
     * @param string $table The name of the table to drop the columns from.
     *
     * @return self
     */
    public function dropColumns($table): self
    {
        foreach ($this->interactions[$table]['columnsToDrop'] as $column) {
            $sql = 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $column;
            $this->database->query($sql);
        }

        return $this;
    }

    /**
     * Remove an index from a table.
     *
     * @param string $name The name of the index to remove.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function dropIndex($name): self
    {
        $table = array_key_last($this->interactions);

        $this->interactions[$table]['indexesToDrop'][] = $name;

        return $this;
    }

    /**
     * dropIndexes
     *
     * @param string $table The name of the table to drop the indexes from.
     *
     * @return self
     */
    public function dropIndexes($table): self
    {
        foreach ($this->interactions[$table]['indexesToDrop'] as $index) {
            $sql = 'ALTER TABLE ' . $table . ' DROP INDEX ' . $index;
            $this->database->query($sql);
        }

        return $this;
    }

    /**
     * Remove a foreign key constraint from a table.
     *
     * @param string $name The name of the foreign key constraint to remove.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function dropForeignKey($name): self
    {
        $table = array_key_last($this->interactions);

        $this->interactions[$table]['foreignKeysToDrop'][] = $name;

        return $this;
    }

    /**
     * dropForeignKeys
     *
     * @param string $table The name of the table to drop the foreign keys from.
     *
     * @return self
     */
    public function dropForeignKeys($table): self
    {
        foreach ($this->interactions[$table]['foreignKeysToDrop'] as $foreignKey) {
            $sql = 'ALTER TABLE ' . $table . ' DROP FOREIGN KEY ' . $foreignKey;
            $this->database->query($sql);
        }

        return $this;
    }

    /**
     * Adds a SQL statement to drop a table.
     *
     * @param string $table The name of the table to drop.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function dropTable($table): self
    {
        $this->caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]['function'];

        $this->interactions[$table]['dropTable'] = true;

        return $this;
    }

    /**
     * Set the type of the column being created or modified.
     *
     * @param string       $type   The type of the column.
     * @param integer|null $length The length of the column (optional).
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function type(string $type, int $length = null): self
    {
        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= ' ' . strtoupper($type);

        if ($length) {
            $this->interactions[$table]['columnsToAdd'][$column] .= "($length)";
        }

        return $this;
    }

    /**
     * boolean
     *
     * @param integer|null $length The length of the column (optional).
     *
     * @return self
     */
    public function boolean($length = 1): self
    {
        $this->type('tinyint', $length);

        return $this;
    }

    /**
     * integer
     *
     * @param integer|null $length The length of the column (optional).
     *
     * @return self
     */
    public function integer($length = 10): self
    {
        $this->type('int', $length);

        return $this;
    }

    /**
     * decimal
     *
     * @param integer|null $length   The length of the column (optional).
     * @param integer|null $decimals The number of decimals (optional).
     *
     * @return self
     */
    public function decimal($length = 10, $decimals = 2): self
    {
        $this->type('decimal', $length . ', ' . $decimals);

        return $this;
    }

    /**
     * string
     *
     * @param integer|null $length The length of the column (optional).
     *
     * @return self
     */
    public function string($length = 255): self
    {
        $this->type('varchar', $length);

        return $this;
    }

    /**
     * text
     *
     * @param integer|null $length The length of the column (optional).
     *
     * @return self
     */
    public function text($length = null): self
    {
        $this->type('text', $length);

        return $this;
    }

    /**
     * timestamp
     *
     * @return self
     */
    public function timestamp(): self
    {
        $this->type('timestamp');

        return $this;
    }

    /**
     * date
     *
     * @return self
     */
    public function date(): self
    {
        $this->type('date');

        return $this;
    }

    /**
     * time
     *
     * @return self
     */
    public function time(): self
    {
        $this->type('time');

        return $this;
    }

    /**
     * Add primaryKey to the current column.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function primaryKey(): self
    {
        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= ' AUTO_INCREMENT PRIMARY KEY';

        return $this;
    }

    /**
     * Set the default value for a column.
     *
     * @param mixed $value The default value to set.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function default(mixed $value): self
    {
        if (is_string($value) && $value !== 'CURRENT_TIMESTAMP') {
            $value = "'$value'";
        }

        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= " DEFAULT $value";

        return $this;
    }

    /**
     * Adds an UNSIGNED attribute to the column being created or modified.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function unsigned(): self
    {
        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= ' UNSIGNED';

        return $this;
    }

    /**
     * Adds a UNIQUE constraint to the current column.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function unique(): self
    {
        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= ' UNIQUE';

        return $this;
    }

    /**
     * Set the column to NULL in the migration SQL statement.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function null(): self
    {
        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= ' NULL';

        return $this;
    }

    /**
     * Set the NOT NULL constraint for the column being created or modified.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function notNull(): self
    {
        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= ' NOT NULL';

        return $this;
    }

    /**
     * Add the "ON UPDATE CURRENT_TIMESTAMP" clause to the SQL statement.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function timestampOnUpdate(): self
    {
        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= ' ON UPDATE CURRENT_TIMESTAMP';

        return $this;
    }

    /**
     * Add a comment to the column being created or modified.
     *
     * @param string $comment The comment to add to the column.
     *
     * @return self Returns the current instance of the Migration object.
     */
    public function comment(string $comment): self
    {
        $table = array_key_last($this->interactions);
        $column = array_key_last($this->interactions[$table]['columnsToAdd']);

        $this->interactions[$table]['columnsToAdd'][$column] .= " COMMENT '$comment'";

        return $this;
    }

    /**
     * Runs the migration by checking if the table exists and adding it if it doesn't,
     * or adding columns if it does. Then adds any indexes or foreign keys that need to be added,
     * drops any columns, indexes, or foreign keys that need to be dropped, and finally executes
     * the prepared query with bound parameters.
     *
     * @return boolean Returns the current instance of the Migration object.
     */
    public function run(): bool
    {
        foreach ($this->interactions as $table => $interaction) {
            if (!empty($interaction['dropTable'])) {
                $sql = 'DROP TABLE IF EXISTS ' . $table;
                $this->database->query($sql);
            } else {
                if ($this->database->tableExists($table)) {
                    $this->addColumns($table);
                    $this->alterTable($table);
                } else {
                    $this->addTable($table);
                }

                if (!empty($interaction['indexesToAdd'])) {
                    $this->addIndexes($table);
                }

                if (!empty($interaction['foreignKeysToAdd'])) {
                    $this->addForeignKeys($table);
                }

                if (!empty($interaction['columnsToDrop'])) {
                    $this->dropColumns($table);
                }

                if (!empty($interaction['indexesToDrop'])) {
                    $this->dropIndexes($table);
                }

                if (!empty($interaction['foreignKeysToDrop'])) {
                    $this->dropForeignKeys($table);
                }
            }
        }

        return true;
    }
}
