<?php
/**
 *
 * @package     Database
 *
 * @subpackage  RecordInterface
 *
 * @author      Sebastian Costiug <sebastian@overbyte.dev>
 * @copyright   2019-2023 Sebastian Costiug
 * @license     https://opensource.org/licenses/BSD-3-Clause
 *
 * @category    Database
 *
 * @since       2023-11-01
 *
 */

namespace database;

/**
 * Record interface
 */
interface RecordInterface
{
    /**
     * Get record by ID
     *
     * @param integer $id The ID of the model to retrieve.
     *
     * @return self|false The model instance if found, or false if not found.
     */
    public static function one(int $id): self|false;

    /**
     * Get all records
     *
     * @return array
     */
    public static function all(): array|false;

    /**
     * Get record by field
     *
     * @param  string $field Field
     * @param  string $value Value
     *
     * @return self|false The model instance if found, or false if not found.
     */
    public static function findBy(string $field, string $value): self|false;

    /**
     * Get records by field
     *
     * @param  string $field Field
     * @param  string $value Value
     *
     * @return array|false An array with all records of the model or false if no records are found.
     */
    public static function findAllBy(string $field, string $value): array|false;

    /**
     * Get record by conditions
     *
     * @param  array $conditions Conditions
     *
     * @return self|false The model instance if found, or false if not found.
     */
    public static function find(array $conditions): self|false;

    /**
     * Get records by conditions
     *
     * @param  array $conditions Conditions
     *
     * @return array|false An array with all records of the model or false if no records are found.
     */
    public static function findAll(array $conditions): array|false;

    /**
     * save record
     *
     * @return integer|false
     */
    public function save(): int|false;

    /**
     * delete record
     *
     * @return boolean
     */
    public function delete(): bool;

    /**
     * Get record relation
     *
     * @param  string      $class    Class
     * @param  string|null $viaClass ViaClass
     *
     * @return object|false
     */
    public function hasOne($class, $viaClass = null): object|false;

    /**
     * Get record relations
     *
     * @param  string      $class    Class
     * @param  string|null $viaClass ViaClass
     *
     * @return array|false
     */
    public function hasMany($class, $viaClass = null): array|false;
}
