<?php

namespace PommePause\Dropinambour\ActiveRecord;

use Exception;
use PommePause\Dropinambour\DB;
use PommePause\Dropinambour\DBQueryBuilder;
use PDOException;
use stdClass;

abstract class AbstractActiveRecord extends stdClass
{
    public const TABLE_NAME  = '';
    protected const PRIMARY_KEY = 'id';

    final public function __construct() {
    }

    /**
     * Override this function to define the columns that we should save NULL values into.
     *
     * @return array
     */
    protected function getNullableFields() : array {
        return [];
    }

    protected function skipParamOnSave(/** @noinspection PhpUnusedParameterInspection */ string $param_name) : bool {
        return FALSE;
    }

    protected function skipParamOnDuplicateKey(/** @noinspection PhpUnusedParameterInspection */ string $param_name) : bool {
        return FALSE;
    }

    /**
     * Insert a new record
     *
     * @return bool
     */
    public function insert() {
        unset($this->{static::PRIMARY_KEY});
        return $this->save(NULL, FALSE);
    }

    /**
     * Update an existing record
     *
     * @param mixed $primary_key_value PK value
     *
     * @return bool
     */
    public function update($primary_key_value) {
        if (empty($primary_key_value)) {
            throw new \Exception('The primary key can\'t be empty when updating an object');
        }

        $this->{static::PRIMARY_KEY} = $primary_key_value;

        $builder = new DBQueryBuilder();
        $builder->update(static::TABLE_NAME)
            ->where(static::PRIMARY_KEY, $primary_key_value);
        $this->setQueryParameters($builder, FALSE);
        $builder->execute();

        return TRUE;
    }

    /**
     * Set the active properties
     *
     * @param DBQueryBuilder $builder Builder
     *
     * @return void
     */
    protected function setQueryParameters($builder, bool $update_if_exists = TRUE) : void {
        $update_columns = [];
        foreach (get_class_vars(static::class) as $property => $value) {
            if ($property == 'object_cache') {
                continue;
            }
            if ($this->skipParamOnSave($property)) {
                continue;
            }
            $value = $this->{$property};
            if ($value !== NULL || array_contains($this->getNullableFields(), $property)) {
                $overridden_method_name = 'setQueryParameter' . ucfirst($property);
                if (method_exists($this, $overridden_method_name)) {
                    $this->{$overridden_method_name}($builder, $value);
                } else {
                    $builder->set($property, $value);
                }
                if (!$this->skipParamOnDuplicateKey($property)) {
                    $update_columns[] = $property;
                }
            }
        }
        if (!empty($update_columns) && $update_if_exists) {
            $builder->onDuplicateKeyUpdate($update_columns);
        }
    }

    /**
     * Populate the properties of the active record from an array
     *
     * @param array $array               Array containing the properties of the current active record class like keys
     * @param array $empty_to_null_props Array of properties names for which we want to use NULL when the value is empty()
     *
     * @return void
     */
    public function populateFromArray(array $array, array $empty_to_null_props = array()) : void {
        foreach ($this as $property => $value) {
            if (array_contains(array_keys($array), $property)) {
                $this->{$property} = $array[$property];
                if (array_contains($empty_to_null_props, $property) && empty($this->{$property})) {
                    $this->{$property} = NULL;
                }
            }
        }
    }

    public function save(?DBQueryBuilder $builder = NULL, bool $update_if_exists = TRUE) : bool {
        if ($builder === NULL) {
            $builder = new DBQueryBuilder();
        }
        $builder->insertInto(static::TABLE_NAME);

        // Will update the row using ON DUPLICATE KEY UPDATE ...
        $this->setQueryParameters($builder, $update_if_exists);

        if (!$update_if_exists) {
            $builder->ignore();
        }

        $new_id = $builder->insert();
        if ($new_id) {
            $this->{static::PRIMARY_KEY} = $new_id;
        }

        if (!$update_if_exists && empty($new_id)) {
            return FALSE;
        }

        return TRUE;
    }

    public function delete() : bool {
        $builder = new DBQueryBuilder();

        try {
            $builder->delete(static::TABLE_NAME)
                ->where(static::PRIMARY_KEY, $this->{static::PRIMARY_KEY})
                ->execute();
        } catch (PDOException $PDOException) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Load one row from the DB, using it's primary key, or any other unique key.
     *
     * @param int|string     $value   Value to look for.
     * @param string         $key     Key; default to the table's primary key.
     * @param DBQueryBuilder $builder DBQueryBuilder used to load data from the database. A new instance will be created if not specified.
     * @param int            $options Options
     *
     * @return bool|static
     */
    public static function getOne($value, ?string $key = NULL, ?DBQueryBuilder $builder = NULL, int $options = 0) {
        if (empty($key)) {
            $key = static::PRIMARY_KEY;
        }
        if (options_contains($options, DB::GET_OPT_CACHED)) {
            $cached_object = static::getFromCache($value, $key);
            if ($cached_object) {
                return $cached_object;
            }
        }
        return DBQueryBuilder::getOneByKey(static::TABLE_NAME, $value, $key, $builder, $options);
    }

    public function reload() {
        $obj = static::getOne($this->{static::PRIMARY_KEY});
        foreach ((array) $obj as $k => $v) {
            if (ord($k[0]) === 0) {
                // private field (of a parent class)!
                continue;
            }
            $this->{$k} = $v;
        }
    }

    /**
     * Create an active record object from a stdClass.
     *
     * @param stdClass $obj Object
     *
     * @return static
     */
    public static function createFromObject(stdClass $obj) {
        $ar = new static();
        foreach ((array) $obj as $k => $v) {
            if (ord($k[0]) === 0) {
                // private field (of a parent class)!
                continue;
            }
            $ar->{$k} = $v;
        }
        return $ar;
    }

    /**
     * Load multiple rows from the DB, using it's primary key, or any other column.
     *
     * @param int|string|array $values      Value(s) to look for.
     * @param string           $key         Key; default to the table's primary key.
     * @param string|null      $index_field If specified, returned array will use this field as indices.
     * @param int              $options     Options
     *
     * @return bool|static[] Array of objects, indexed by primary key. Or FALSE if no rows were found matching the specified values.
     */
    public static function getMany($values, ?string $key = NULL, $index_field = NULL, $options = 0) {
        if (empty($key)) {
            $key = static::PRIMARY_KEY;
        }
        if (empty($index_field)) {
            $index_field = static::PRIMARY_KEY;
        }
        return DBQueryBuilder::getManyByKey(static::TABLE_NAME, $values, $key, $index_field, $options);
    }

    public static function unboxRow(&$row) : void {
        foreach ($row as $property => $value) {
            $overridden_method_name = 'unbox' . ucfirst($property);
            if (method_exists($row, $overridden_method_name)) {
                $row->{$overridden_method_name}();
            }
        }
    }

    public function unbox() : void {
        static::unboxRow($this);
    }

    /**
     * Load one row from the DB, using it's primary key, and remove its PK, in order to be able to create a new row when it is saved.
     *
     * @param int|string $value Value to look for.
     * @param string     $key   Key; default to the table's primary key.
     *
     * @return static
     * @throws Exception When object doesn't exist
     */
    public static function cloneOne($value, ?string $key = NULL) {
        $obj = static::getOne($value, $key);
        if (!$obj) {
            throw new Exception("Object not found.");
        }
        unset($obj->{$obj::PRIMARY_KEY});
        return $obj;
    }

    public function cloneThis() : self {
        unset($this->{$this::PRIMARY_KEY});
        return $this;
    }

    protected static $object_cache = [];
    public static function cache($objects) {
        $objects = to_array($objects);
        $type = get_class(first($objects));
        foreach ($objects as $object) {
            $id = $object->{$object::PRIMARY_KEY};
            static::$object_cache[$type][$id] = $object;
        }
    }

    /**
     * @param int|string $value Value to look for.
     * @param string     $key   Key; default to the table's primary key.
     *
     * @return static|false
     */
    public static function getFromCache($value, $key = NULL) {
        if (!@is_array(static::$object_cache[static::class])) {
            return FALSE;
        }
        if (empty($key)) {
            $key = static::PRIMARY_KEY;
        }
        if ($key == static::PRIMARY_KEY) {
            return static::$object_cache[static::class][$value] ?? FALSE;
        }
        foreach (static::$object_cache[static::class] as $o) {
            if ($o->{$key} == $value) {
                return $o;
            }
        }
        return FALSE;
    }

    /**
     * @param string      $q           Query
     * @param array       $args        Parameters
     * @param string|null $index_field If defined, return an array with this column's values as indices
     * @param int         $options     Options
     *
     * @return static[]
     */
    public static function getAll(string $q, $args = [], ?string $index_field = NULL, int $options = 0) {
        return DB::getAll($q, $args, $index_field, $options, static::class);
    }

    /**
     * @param string $q       Query
     * @param array  $args    Parameters
     * @param int    $options Options
     *
     * @return static
     */
    public static function getFirst(string $q, $args = [], int $options = 0) {
        return DB::getFirst($q, $args, $options, static::class);
    }
}
