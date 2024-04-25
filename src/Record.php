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
 * Record trait
 */
trait Record
{
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
    public function getColumns()
    {
        return $this->database->getColumns(static::$table);
    }
}
