<?php
/**
 *
 * @package     slim-base
 *
 * @subpackage  Model
 *
 * @author      Sebastian Costiug <sebastian@overbyte.dev>
 * @copyright   2019-2023 Sebastian Costiug
 * @license     https://opensource.org/licenses/BSD-3-Clause
 *
 * @category    slim-base
 * @see
 *
 * @since       2023-10-30
 *
 */

namespace database;

use AllowDynamicProperties;

/**
 * Model class
 */
#[AllowDynamicProperties]
class Model implements RecordInterface
{
    /**
     * @var array $fillable Fillable
     */
    public array $fillable = ['id', 'created', 'updated'];

    /**
     * @var array $labels Labels
     */
    public array $labels = [
        'id'      => 'ID',
        'created' => 'Created',
        'updated' => 'Updated',
    ];

    /**
     * Magic method to get a property value by calling its corresponding getter method.
     *
     * @param string $name The name of the property to get.
     *
     * @return mixed The value of the property. Null if the property does not exist or is write-only.
     */
    public function __get($name): mixed
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return null;
    }

    /**
     * afterFind
     *
     * @return $this
     */
    public function afterFind()
    {
        $this->setOldAttributes();

        return $this;
    }

    /**
     * beforeValidate
     *
     * @return void
     */
    public function beforeValidate()
    {
        if ($this->isNewRecord()) {
            foreach ($this->defaults as $key => $value) {
                if (!isset($this->$key)) {
                    $this->$key = $value;
                }
            }
        }
        $this->applyFilters();
    }

    /**
     * validate
     *
     * @return boolean
     */
    public function validate()
    {
        $this->beforeValidate();
        $this->validateRules();

        return empty($this->errors);
    }

    /**
     * This method is called before inserting a record.
     * Override this method to add custom logic before saving.
     *
     * @param boolean $insert Insert
     *
     * @return boolean
     */
    public function beforeSave($insert)
    {
        if (!$this->isNewRecord()) {
            $this->setChangedAttributes();
        }

        return true;
    }

    /**
     * This method is called after inserting a record.
     * Override this method to add custom logic after saving.
     *
     * @param boolean $insert Insert
     *
     * @return boolean
     */
    public function afterSave($insert)
    {
        return true;
    }

    /**
     * beforeDelete
     *
     * @return boolean
     */
    public function beforeDelete()
    {
        return true;
    }

    /**
     * afterDelete
     *
     * @return boolean
     */
    public function afterDelete()
    {
        return true;
    }

    /**
     * Returns a single model instance
     *
     * @param  integer $id ID
     *
     * @return self|false The model instance if found, or false if not found.
     */
    public static function one($id): self|false
    {
        $record = new Record(static::class);
        $data = $record->find()->where(['id' => $id])->one();

        if (empty($data)) {
            return false;
        }

        return static::loadData($data)->afterFind();
    }

    /**
     * Get all records of the model
     *
     * @return array|false An array with all records of the model or false if no records are found.
     */
    public static function all(): array|false
    {
        $record = new Record(static::class);
        $fetchData = $record->find()->all();

        if (empty($fetchData)) {
            return false;
        }

        $results = [];
        foreach ($fetchData as $data) {
            $model = static::loadData($data)->afterFind();
            $results[] = $model;
        }

        return $results;
    }

    /**
     * Find a record by a specific field and value
     *
     * @param  string $field Field
     * @param  string $value Value
     *
     * @return self|false The model instance if found, or false if not found.
     */
    public static function findBy(string $field, string $value): self|false
    {
        $record = new Record(static::class);
        $fetchData = $record->find()->where([$field => $value])->one();

        if (empty($fetchData)) {
            return false;
        }

        return static::loadData($fetchData)->afterFind();
    }

    /**
     * Find a record by ID
     *
     * @param  integer $id ID
     *
     * @return self|false The model instance if found, or false if not found.
     */
    public static function findById(int $id): self|false
    {
        $record = new Record(static::class);
        $fetchData = $record->find()->where(['id' => $id])->one();

        if (empty($fetchData)) {
            return false;
        }

        return static::loadData($fetchData)->afterFind();
    }

    /**
     * Find all records by a specific field and value
     *
     * @param  string      $field Field
     * @param  string|null $value Value
     *
     * @return array|false An array with all records of the model or false if no records are found.
     */
    public static function findAllBy(string $field, string|null $value): array|false
    {
        if (empty($value)) {
            return false;
        }
        $record = new Record(static::class);
        $fetchData = $record->find()->where([$field => $value])->all();

        if (empty($fetchData)) {
            return false;
        }

        $results = [];
        foreach ($fetchData as $data) {
            $model = static::loadData($data)->afterFind();
            $results[] = $model;
        }

        return $results;
    }

    /**
     * Find a record by conditions
     *
     * @param  array $conditions Conditions
     *
     * @return self|false The model instance if found, or false if not found.
     */
    public static function find(array $conditions): self|false
    {
        $record = new Record(static::class);
        $fetchData = $record->find()->where($conditions)->one();

        if (empty($fetchData)) {
            return false;
        }

        return static::loadData($fetchData)->afterFind();
    }

    /**
     * Find all records by conditions
     *
     * @param  array $conditions Conditions
     *
     * @return array|false An array with all records of the model or false if no records are found.
     */
    public static function findAll(array $conditions = []): array|false
    {
        $record = new Record(static::class);
        $fetchData = $record->find()->where($conditions)->all();

        if (empty($fetchData)) {
            return false;
        }

        $results = [];
        foreach ($fetchData as $data) {
            $model = static::loadData($data)->afterFind();
            $results[] = $model;
        }

        return $results;
    }

    /**
     * Save the record
     *
     * @return integer|false The ID of the saved record, or false if the record failed to save.
     */
    public function save(): int|false
    {
        $record = new Record(static::class);

        if (!$this->validate()) {
            return false;
        }

        $this->beforeSave($this->isNewRecord());

        $id = $record->setAttributes($this->attributes())->save();

        $this->afterSave($this->isNewRecord());

        return $id;
    }

    /**
     * Delete the record
     *
     * @return boolean True if the record is successfully deleted, false otherwise.
     */
    public function delete(): bool
    {
        $this->beforeDelete();

        $record = new Record(static::class);
        $record->setAttributes($this->attributes());

        if (!$record->delete()) {
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
     * @return object|false The related record if found, or false if not found.
     */
    public function hasOne($class, $viaTable = null): object|false
    {
        $record = new Record(static::class);
        $record->has($class);

        if ($viaTable) {
            $record = $record->via($viaTable);
        }

        return $record->one();
    }

    /**
     * Get record relation using hasMany relationship
     *
     * @param  string      $class    Class
     * @param  string|null $viaTable Intermediate table
     *
     * @return array|false An array with all related records, or false if no records are found.
     */
    public function hasMany($class, $viaTable = null): array|false
    {
        $record = new Record(static::class);
        $record->has($class);

        if ($viaTable) {
            $record = $record->via($viaTable);
        }

        return $record->all();
    }

    /**
     * setAttributes
     *
     * @param array $attributes Attributes
     *
     * @return void
     */
    public function setAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * getAttributes
     *
     * @return array
     */
    public function getAttributes(): array
    {
        $attributes = [];

        foreach ($this->fillable as $key) {
            if (isset($this->$key) && !empty($this->$key) && !in_array($key, $this->hidden)) {
                $attributes[$key] = $this->$key;
            }
        }

        return $attributes;
    }

    /**
     * Set the old attributes of the model
     *
     * @return void
     */
    public function setOldAttributes()
    {
        $this->oldAttributes = !$this->isNewRecord() ? $this->attributes() : null;
    }

    /**
     * Set the changed attributes of the model
     *
     * @return void
     */
    public function setChangedAttributes()
    {
        $this->changedAttributes = array_intersect_assoc($this->attributes(), $this->oldAttributes);
    }

    /**
     * Check if the model has an attribute with the specified name
     *
     * @param string $name The name of the attribute
     *
     * @return boolean Whether the model has an attribute with the specified name.
     */
    public function hasAttribute($name)
    {
        return property_exists($this, $name);
    }

    /**
     * Validate the model rules
     *
     * @return void
     */
    public function validateRules()
    {
        $this->errors = [];

        $rules = $this->has('scenario') ? $this->rules[$this->scenario] : $this->rules;

        foreach ($rules as $enforce => $fields) {
            switch ($enforce) {
                case 'required':
                    foreach ($fields as $field) {
                        if (empty($this->$field)) {
                            $this->errors[$field][] = $this->labels[$field] . ' is required';
                        }
                    }
                    break;

                case 'email':
                    foreach ($fields as $field) {
                        if (!filter_var($this->$field, FILTER_VALIDATE_EMAIL)) {
                            $this->errors[$field][] = $this->labels[$field] . ' is not a valid email address';
                        }
                    }
                    break;

                case 'unique':
                    foreach ($fields as $field) {
                        $user = static::findAllBy($field, $this->$field);
                        if ($user) {
                            $this->errors[$field][] = $this->labels[$field] . ' already exists';
                        }
                    }
                    break;

                case 'lengthMin':
                    foreach ($fields as $field => $rule) {
                        if (strlen($this->$field ?? '') < $rule) {
                            $this->errors[$field][] = $this->labels[$field] . ' must be at least ' . $rule . ' characters long';
                        }
                    }
                    break;

                case 'lengthMax':
                    foreach ($fields as $field => $rule) {
                        if (strlen($this->$field ?? '') > $rule) {
                            $this->errors[$field][] = $this->labels[$field] . ' must be at most ' . $rule . ' characters long';
                        }
                    }
                    break;

                case 'strength':
                    foreach ($fields as $field) {
                        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(_|[^\w])).+$/', $this->$field ?? '')) {
                            $this->errors[$field][] = $this->labels[$field] . ' must contain at least one lowercase letter, one uppercase letter, one digit and one special character';
                        }
                    }
                    break;

                case 'in':
                    foreach ($fields as $field => $values) {
                        if (!in_array($this->$field, $values)) {
                            $this->errors[$field][] = $this->labels[$field] . ' is not valid';
                        }
                    }
                    break;

                case 'compare':
                    foreach ($fields as $field => $compare) {
                        if ($this->$field !== $this->$compare || !password_verify($this->$compare, $this->$field)) {
                            $this->errors[$field][] = $this->labels[$field] . ' does not match';
                        }
                    }
                    break;

                default:
                    break;
            }
        }
    }

    /**
     * Apply filters to the model attributes
     *
     * @return void
     */
    public function applyFilters()
    {
        foreach ($this->filters as $filter => $fields) {
            foreach ($fields as $field) {
                if (!isset($this->$field)) {
                    continue;
                }
                switch ($filter) {
                    case 'trim':
                        $this->$field = trim($this->$field);
                        break;

                    case 'stripTags':
                        $this->$field = strip_tags($this->$field);
                        break;

                    case 'lowercase':
                        $this->$field = strtolower($this->$field);
                        break;

                    case 'hash':
                        if ($this->has('scenario') && $this->scenario !== 'login') {
                            $this->$field = password_hash($this->$field, PASSWORD_BCRYPT);
                        }
                        break;

                    default:
                        break;
                }
            }
        }
    }

    /**
     * Load data into a new instance of the model.
     *
     * @param array $data The data to load into the model.
     *
     * @return self The new instance of the model with the loaded data.
     */
    public static function loadData(array $data): self
    {
        $model = new static();
        foreach ($data as $key => $value) {
            $model->$key = $value;
        }

        return $model;
    }

    /**
     * Get the attributes that correspond to columns from the database
     *
     * @return array
     */
    public function attributes()
    {
        $attributes = [];

        foreach ($this->fillable as $field) {
            $attributes[$field] = $this->$field;
        }

        return $attributes;
    }

    /**
     * Get the validation errors
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
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
     * Get the last inserted ID
     *
     * @return integer
     */
    public function lastInsertId()
    {
        $record = new Record(static::class);
        return $record->lastInsertId();
    }

    /**
     * Add values to an array attribute
     *
     * @param string $array  The array attribute to add to
     * @param array  $values The values to add
     *
     * @return void
     */
    public function add($array, array $values)
    {
        if (!isset($this->$array)) {
            $this->$array = [];
        }

        $this->$array = array_merge($this->$array, $values);
    }

    /**
     * Add an error to the model
     *
     * @param string $field   The field to add the error to
     * @param string $message The error message
     *
     * @return void
     */
    public function addError($field, $message)
    {
        $this->add('errors', [$field => $message]);
    }

    /**
     * Check if the model has a specific attribute.
     *
     * @param string $attribute The attribute to check.
     *
     * @return boolean Returns true if the attribute exists, false otherwise.
     */
    public function has($attribute)
    {
        return property_exists($this, $attribute);
    }
}
