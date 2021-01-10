<?php

namespace PommePause\Dropinambour;

use Exception;
use PommePause\Dropinambour\ActiveRecord\AbstractActiveRecord;
use stdClass;

class DBQueryBuilder
{
    private $_op = NULL;
    private const OP_SELECT = 1;
    private const OP_UPDATE = 2;
    private const OP_INSERT = 3;
    private const OP_DELETE = 4;

    private $_table_refs = [];
    private $_params = [];

    private $_cached = FALSE;

    // SELECT
    private $_select_expr = [];
    private $_joins = [];
    private $_where_conditions = [];
    private $_group_by = [];
    private $_having_conditions = [];
    private $_order_by = [];
    private $_limit = '';

    // INSERT, UPDATE
    private $_col_values = [];
    private $_on_duplicate_key_update_columns = [];
    private $_option = '';

    public function __construct() {
    }

    /**
     * Save to cache the values, before returning them. Or, if the cache already contains a hit, return the cached values.
     * Usage: $builder->select()->from()->where()->cached()->getFirst|getFirstValue|getAll|getAllValues()
     *
     * @param bool $cache_enabled Specify FALSE to disable the cache (default). Specify TRUE, or pass no parameters, to enable the cache.
     *
     * @return DBQueryBuilder
     */
    public function cached(bool $cache_enabled = TRUE) : self {
        $this->_cached = $cache_enabled;
        return $this;
    }

    /**
     * Defines the select expression for the query.
     * Usage: $builder->select()->from()->where()->groupBy()->having()->orderBy()->limit()->getFirst|getFirstValue|getAll|getAllValues()
     *
     * @param string $select_expr Select expression
     *
     * @return DBQueryBuilder
     */
    public function select(string $select_expr) : self {
        $this->_op = static::OP_SELECT;
        $this->_select_expr[] = $select_expr;
        return $this;
    }

    /**
     * Defines the COUNT expression for the query.
     * Usage: $builder->count()->from()->where()->getFirstValue()
     *
     * @param string $count_expr COUNT expression; defaults to '*'
     *
     * @return DBQueryBuilder
     */
    public function count(string $count_expr = '*') : self {
        $this->_op = static::OP_SELECT;
        $this->_select_expr[] = "COUNT($count_expr)";
        return $this;
    }

    /**
     * Checks if a specific row exists in a table.
     * Usage: $builder->exists()->where()->doExists()
     *
     * @param string      $table_ref Table reference
     * @param string|NULL $alias     Optional table alias
     *
     * @return DBQueryBuilder
     */
    public function exists(string $table_ref, ?string $alias = NULL) : self {
        $this->select('1')->from($table_ref, $alias);
        return $this;
    }

    /**
     * Defines the table reference for a SELECT query.
     * Examples: $builder->from("table1")
     *           $builder->from("table1", "t1")->from("table2", "t2")->where("t1.col1 = t2.col2")
     *
     * @param string      $table_ref Table reference
     * @param string|NULL $alias     Optional table alias
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function from(string $table_ref, ?string $alias = NULL) : self {
        $this->_table_refs[] = static::_escapeColumnName($table_ref) . (!empty($alias) ? ' ' . static::_escapeColumnName($alias) : '');
        return $this;
    }

    /**
     * Use another DBQueryBuilder as a sub-select.
     * Examples: $builder1->select("*")->from("table1")
     *           $builder2->select("a.*")->fromBuilder($builder1, "a")
     *
     * @param DBQueryBuilder $builder DBQueryBuilder
     * @param string         $alias   Sub-select alias
     *
     * @return DBQueryBuilder
     */
    public function fromBuilder(DBQueryBuilder $builder, string $alias) : self {
        $sub_select = $builder->build();
        $sub_select_params = $builder->getParams();
        $this->from("($sub_select)", $alias);
        $this->addParams($sub_select_params);
        return $this;
    }

    /**
     * Helper method to create a JOIN in a SELECT query.
     * Equivalent to $builder->from()->where()
     *
     * @param string $table_ref       Table reference
     * @param string $where_condition Where expression
     * @param array  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     */
    public function join(string $table_ref, string $where_condition, ...$param_values) : self {
        $this->_applyParams($where_condition, $param_values);
        $this->_joins["JOIN " . static::_escapeColumnName($table_ref)] = $where_condition;
        return $this;
    }

    /**
     * Add left (outer) join to a SELECT query.
     * Equivalent to $builder->from()->leftJoin()
     *
     * @param string $table_ref       Table reference
     * @param string $join_condition  Where expression for the JOIN
     * @param mixed  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     */
    public function leftJoin(string $table_ref, string $join_condition, ...$param_values) : self {
        $this->_applyParams($join_condition, $param_values);
        $this->_joins["LEFT JOIN " . static::_escapeColumnName($table_ref)] = $join_condition;
        return $this;
    }

    /**
     * Add left (outer) join to a SELECT query, and a WHERE condition to return only rows that are NOT in the specified table.
     * Example: $builder->from()->excludeJoin()
     *
     * @param string $table_ref       Table reference
     * @param string $join_condition  Where expression for the JOIN
     * @param string $null_column     Column (from the joined table) that should be NULL
     * @param mixed  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     */
    public function excludeJoin(string $table_ref, string $join_condition, string $null_column, ...$param_values) : self {
        $this->_applyParams($join_condition, $param_values);
        $this->leftJoin($table_ref, $join_condition)
            ->where("$null_column IS NULL");
        return $this;
    }

    /**
     * Defines the where condition for a SELECT query.
     * Examples: $builder->where("col1", $val1)
     *           $builder->where("col2 = 2")
     *           $builder->where("col3 = :val3", 3)
     *           $builder->where("col4 = :val4 AND col5 = :val5", 4, 5)
     *           $builder->where("table1.col1 = table2.col2")
     *
     * @param string $where_condition Where expression
     * @param mixed  ...$param_values Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function where(string $where_condition, ...$param_values) : self {
        $this->_applyParams($where_condition, $param_values);
        $this->_where_conditions[] = $where_condition;
        return $this;
    }

    /**
     * Use the full-text index on the specified column to match against the specified words/query.
     * Examples: $builder->matchAgainstWords("col1", $val1)
     *
     * @param string $column              Column name
     * @param string $query               Space-separated list of words to match against the specified column. Note that 1-character words will be ignored.
     * @param bool   $allow_partial_words Match against partial words? eg. 'pata' would match 'patate', when TRUE. Otherwise, this function will only match against full words. Note that the specified query will only match partial words that begins with the specified terms. eg. 'tate' will NEVER match 'patate'.
     *
     * @return DBQueryBuilder
     */
    public function matchAgainstWords(string $column, string $query, bool $allow_partial_words = TRUE) : self {
        $match_against = [];
        if (string_contains($query, '-')) {
            $query = str_replace('-', ' ', $query);
        }
        $query = trim($query);
        foreach (explode(' ', $query) as $word) {
            if (strlen($word) < 2) {
                // Skip 1 characters words; FT won't be able to match against those
                continue;
            }
            $suffix = ( $allow_partial_words ? '*' : '' );
            $match_against[] = '+' . $word . $suffix;
        }

        if (empty($match_against)) {
            // Return empty array
            $this->where("1 = 2");
            return $this;
        }

        $this->where("MATCH (" . static::_escapeColumnName($column) . ") AGAINST (:filter IN BOOLEAN MODE)", implode(' ', $match_against));
        return $this;
    }

    /**
     * Defines the grouping expression for a SELECT query.
     * Examples: $builder->groupBy("col1")
     *           $builder->groupBy("col2, col3")
     *           $builder->groupBy("1, 3, 2")
     *
     * @param string $group_by Grouping expression
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function groupBy(string $group_by) : self {
        $this->_group_by[] = $group_by;
        return $this;
    }

    /**
     * Defines the where expression for a HAVING clause of a SELECT query.
     * Examples: $builder->having("col1")
     *           $builder->groupBy("col2 ASC, col3 DESC")
     *
     * @param string $having_condition Where expression for the having clause
     * @param string ...$param_values  Optional parameter values, if the where condition contains placeholders
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     * @see DBQueryBuilder::where() for examples.
     */
    public function having(string $having_condition, ...$param_values) : self {
        if (!empty($param_values)) {
            if (preg_match_all('/:([a-z0-9_]+)/i', $having_condition, $re)) {
                $i = 0;
                if (count($re[1]) != count($param_values)) {
                    die("Error: number of arguments is different from number of placeholders");
                }
                foreach ($param_values as $param_value) {
                    $this->_addParam($re[1][$i++], $param_value, $having_condition);
                }
            } else {
                die("Error: having() requires placeholders in condition when specifying param value(s)");
            }
        }
        $this->_having_conditions[] = $having_condition;
        return $this;
    }

    /**
     * Defines the ordering expression for a SELECT query.
     * Examples: $builder->orderBy("col1")
     *           $builder->orderBy("col2 ASC, col3 DESC")
     *
     * @param string $order_by        Ordering expression
     * @param array  ...$param_values Optional parameter values, if the order by condition contains placeholders
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function orderBy(string $order_by, ...$param_values) : self {
        if (!empty($param_values)) {
            if (preg_match_all('/:([a-z0-9_]+)/i', $order_by, $re)) {
                $i = 0;
                if (count($re[1]) != count($param_values)) {
                    die("Error: number of arguments is different from number of placeholders");
                }
                foreach ($param_values as $param_value) {
                    $this->_addParam($re[1][$i++], $param_value, $order_by);
                }
            } else {
                die("Error: orderBy() requires placeholders in condition when specifying param value(s)");
            }
        }
        $this->_order_by[] = $order_by;
        return $this;
    }

    /**
     * Defines the limiting expression for a SELECT query.
     * Examples: $builder->limit("1")
     *           $builder->limit("1, 100")
     *
     * @param string $limit Limiting expression
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function limit(string $limit) : self {
        $this->_limit = $limit;
        return $this;
    }

    /**
     * Defines the table(s) to use in an UPDATE query.
     * Usage: $builder->update()->set()->where()->execute()
     *
     * @param string $table_ref Table(s) to update
     *
     * @return DBQueryBuilder
     */
    public function update(string $table_ref) : self {
        $this->_op = static::OP_UPDATE;
        $this->_table_refs[] = static::_escapeColumnName($table_ref);
        return $this;
    }

    /**
     * Defines the table to use in an INSERT query.
     * Usage: $builder->insertInto()->ignore()->set()->insert()
     *
     * @param string $table_ref Table to insert into
     *
     * @return DBQueryBuilder
     */
    public function insertInto(?string $table_ref = NULL) : self {
        $this->_op = static::OP_INSERT;
        if (!empty($table_ref)) {
            $this->_table_refs[] = static::_escapeColumnName($table_ref);
        }
        return $this;
    }

    /**
     * Add the IGNORE keyword in an INSERT query.
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::insert() for usage.
     */
    public function ignore() : self {
        $this->_option = ' IGNORE';
        return $this;
    }

    /**
     * Defines the values to set for INSERT and UPDATE queries.
     * Examples: $builder->set("col1", $val1)
     *           $builder->set(["col2", "col3"], $val2, $val3)
     *           $builder->set("col4", 4)
     *           $builder->set("col5 = :val5", $val5)
     *
     * @param string|array $column          Column name (string) or column names (array of string)
     * @param mixed        ...$param_values Value(s) for the specified column(s)
     *
     * @return DBQueryBuilder
     *
     * @see DBQueryBuilder::insertInto() for usage in INSERT query.
     * @see DBQueryBuilder::update() for usage in UPDATE query.
     */
    public function set($column, ...$param_values) : self {
        if (is_array($column)) {
            $array = $column;
            foreach ($array as $column_name => $param_value) {
                $this->set($column_name, $param_value);
            }
            return $this;
        }
        if (!empty($param_values)) {
            if (preg_match_all('/:([a-z0-9_]+)/i', $column, $re)) {
                if (count($re[1]) != count($param_values)) {
                    die("Error: number of arguments is different from number of placeholders");
                }
                $i = 0;
                foreach ($param_values as $param_value) {
                    $this->_addParam($re[1][$i++], $param_value);
                }
            } elseif (count($param_values) == 1) {
                if (preg_match('/^[a-z0-9_]+\.([a-z0-9_]+)$/i', $column, $re) || preg_match('/^([a-z0-9_]+)$/i', $column, $re)) {
                    $this->_addParam($re[1], $param_values[0]);
                    $column = static::_escapeColumnName($column) . " = :" . $re[1];
                } else {
                    die("Error: can't find where to inject param value in '$column'.");
                }
            } else {
                die("Error: placeholders params needed when specifying multiple param values.");
            }
        }
        $this->_col_values[] = $column;
        return $this;
    }

    /**
     * Helper method to use DBQueryBuilder::set() using an array.
     * Example: $builder->setFromArray(["col1" => $val1, "col2" => 2])
     *
     * @param array|stdClass $params Array of values to set, with the column names as indices. If an object is specified, it will be cast into an array.
     *
     * @return DBQueryBuilder
     */
    public function setFromArray($params) : self {
        if ($params instanceof stdClass) {
            $params = (array) $params;
        }
        foreach ($params as $column => $value) {
            $this->set($column, $value);
        }
        return $this;
    }

    /**
     * Add the specified value to a SET column.
     * Example: $builder->addToSet('flags', 2)
     *
     * @param string $column       Column name
     * @param int    $value_to_add Value to add; specify the numeric (power of 2) value to add, not the textual value.
     *
     * @return DBQueryBuilder
     */
    public function addToSet(string $column, int $value_to_add) : self {
        $this->set("`$column` = `$column` + $value_to_add");
        // Don't try to add this value if the column already contains it (it would fail)
        $this->where("NOT (`$column` & $value_to_add)");
        return $this;
    }

    /**
     * Remove the specified value from a SET column.
     * Example: $builder->removeFromSet('flags', 2)
     *
     * @param string $column          Column name
     * @param int    $value_to_remove Value to remove; specify the numeric (power of 2) value to remove, not the textual value.
     *
     * @return DBQueryBuilder
     */
    public function removeFromSet(string $column, int $value_to_remove) : self {
        $this->set("`$column` = `$column` & ~$value_to_remove");
        return $this;
    }

    /**
     * Add the ON DUPLICATE KEY UPDATE clause to an INSERT query.
     *
     * @param string ...$columns The columns to update, if the row already exists
     *
     * @return DBQueryBuilder
     * @throws Exception
     *
     * @see DBQueryBuilder::insert() for usage.
     */
    public function onDuplicateKeyUpdate(...$columns) : self {
        if ($this->_op != static::OP_INSERT) {
            throw new Exception("onDuplicateKeyUpdate() is only available for INSERT queries.");
        }
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }
        $this->_on_duplicate_key_update_columns = $columns;
        return $this;
    }

    /**
     * Checks if the builder object has one or more values to UPDATE or INSERT.
     * Use this before you call execute() or insert() on a builder object that was built dynamically.
     *
     * @return bool TRUE if the builder has one or more values to UPDATE or INSERT.
     */
    public function hasUpdates() : bool {
        return !empty($this->_col_values);
    }

    /**
     * Builds the SQL query.
     *
     * @return string SQL query
     *
     * @throws Exception
     */
    public function build() : string {
        switch ($this->_op) {
        case static::OP_SELECT:
            $q = "SELECT " . implode(', ', $this->_select_expr) . " FROM " . implode(', ', $this->_table_refs);
            foreach ($this->_joins as $table_ref => $where_condition) {
                $q .= " $table_ref ON ($where_condition)";
            }
            if (!empty($this->_where_conditions)) {
                $q .= " WHERE (" . implode(') AND (', $this->_where_conditions) . ")";
            }
            if (!empty($this->_group_by)) {
                $q .= " GROUP BY " . implode(', ', $this->_group_by);
            }
            if (!empty($this->_having_conditions)) {
                $q .= " HAVING (" . implode(') AND (', $this->_having_conditions) . ")";
            }
            if (!empty($this->_order_by)) {
                $q .= " ORDER BY " . implode(', ', $this->_order_by);
            }
            if (!empty($this->_limit)) {
                $q .= " LIMIT " . $this->_limit;
            }
            break;
        case static::OP_UPDATE:
            if (empty($this->_where_conditions)) {
                throw new Exception("UPDATE queries need WHERE conditions");
            }
            $q = "UPDATE" . $this->_option . " " . implode(', ', $this->_table_refs) . " SET " . implode(', ', $this->_col_values) . " WHERE (" . implode(') AND (', $this->_where_conditions) . ")";
            break;
        case static::OP_INSERT:
            $q = "INSERT" . $this->_option . " INTO " . implode(', ', $this->_table_refs) . " SET " . implode(', ', $this->_col_values);
            if (!empty($this->_on_duplicate_key_update_columns)) {
                $values = [];
                foreach ($this->_on_duplicate_key_update_columns as $col) {
                    $values[] = static::_escapeColumnName($col) . " = VALUES(" . static::_escapeColumnName($col) . ")";
                }
                $q .= " ON DUPLICATE KEY UPDATE " . implode(", ", $values);
            }
            break;
        case static::OP_DELETE:
            /** @noinspection SqlWithoutWhere */
            $q = "DELETE FROM " . implode(', ', $this->_table_refs);
            $q .= " WHERE (" . implode(') AND (', $this->_where_conditions) . ")";
            break;
        default:
            throw new Exception("Invalid query operation");
        }
        return $q;
    }

    /**
     * Returns the parameters to use alongside the SQL query.
     *
     * @return array The parameters to use when executing the SQL query
     */
    public function getParams() : array {
        return $this->_params;
    }

    /**
     * Inject params, for example for a sub-select created by another DBQueryBuilder.
     * Example: $builder->addParams($params)
     *
     * @param array $params Params to add
     *
     * @return DBQueryBuilder
     */
    protected function addParams(array $params) : self {
        foreach ($params as $key => $value) {
            $this->_params[$key] = $value;
        }
        return $this;
    }

    protected function setParams(array $params) : self {
        $this->_params = $params;
        return $this;
    }

    /**
     * Executes the query.
     *
     * @return void
     */
    public function execute() : void {
        if ($this->_op == static::OP_UPDATE && !$this->hasUpdates()) {
            return;
        }
        DB::execute($this->build(), $this->getParams());
    }

    /**
     * Executes an INSERT query.
     *
     * @return int|bool The autoincrement ID of the new row.
     *
     * @see DBQueryBuilder::insertInto() for usage.
     */
    public function insert() {
        return DB::insert($this->build(), $this->getParams());
    }

    /**
     * Defines the table to use in a DELETE query.
     * Usage: $builder->delete()->where()->execute()
     *
     * @param string $table_ref Table to delete
     *
     * @return DBQueryBuilder
     */
    public function delete(string $table_ref) : self {
        $this->_op          = static::OP_DELETE;
        $this->_table_refs[] = static::_escapeColumnName($table_ref);

        return $this;
    }

    /**
     * Returns an object corresponding to the first row returned by a SELECT query, or FALSE if nothing matched the where expression.
     *
     * @param null|string $class_type If not empty, the object returned will be of the specified class.
     *
     * @return bool|stdClass An object corresponding to the first row returned by a SELECT query, or FALSE if nothing matched the where expression.
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function getFirst(?string $class_type = NULL) {
        $options = ( $this->_cached ? DB::GET_OPT_CACHED : 0 );
        return DB::getFirst($this->build(), $this->getParams(), $options, $class_type);
    }

    /**
     * Returns a value corresponding to the first column of the first row returned by a SELECT query, or FALSE if nothing matched the where expression.
     *
     * @return bool|string A (string) value corresponding to the first column of the first row returned by a SELECT query, or FALSE if nothing matched the where expression.
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function getFirstValue() {
        $options = ( $this->_cached ? DB::GET_OPT_CACHED : 0 );
        return DB::getFirstValue($this->build(), $this->getParams(), $options);
    }

    /**
     * Returns an array of objects corresponding to the rows returned by a SELECT query.
     *
     * @param string|null $index_field If specified, the returned array will use the values of this select expression as the indices.
     * @param int         $options     Specify GET_OPT_INDEXED_ARRAYS if you'd like the returned array to be an array of arrays.
     * @param null|string $class_type  If not empty, returned objects will be of the specified class.
     *
     * @return array An array of objects corresponding to the rows returned by a SELECT query.
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function getAll(?string $index_field = NULL, int $options = 0, ?string $class_type = NULL) : array {
        if ($this->_cached) {
            $options = $options | DB::GET_OPT_CACHED;
        }
        return DB::getAll($this->build(), $this->getParams(), $index_field, $options, $class_type);
    }

    public function getNumRowsTotal() : int {
        $limit_copy = $this->_limit;
        $this->limit('');

        $builder = new self();
        $builder->count('*')
            ->from('(' . $this->build() . ')', 'a')
            ->setParams($this->getParams());
        $num_rows = $builder->getFirstValue();

        $this->limit($limit_copy);

        return $num_rows;
    }

    /**
     * Returns an array of values corresponding to the first column of the rows returned by a SELECT query.
     *
     * @param string|null $data_type Will cast the values into the specified type. If not specified, returned values will be strings.
     *
     * @return array An array of values corresponding to the first column of the rows returned by a SELECT query.
     *
     * @see DBQueryBuilder::select() for usage.
     */
    public function getAllValues(?string $data_type = NULL) : array {
        $options = ( $this->_cached ? DB::GET_OPT_CACHED : 0 );
        return DB::getAllValues($this->build(), $this->getParams(), $data_type, $options);
    }

    /**
     * Checks if the specified where expression matched a row.
     *
     * @return bool TRUE if the specified where expression matched a row.
     *
     * @see DBQueryBuilder::exists() for usage.
     */
    public function doExists() : bool {
        return ( $this->getFirstValue() === '1' );
    }

    /**
     * Helper method to SELECT * FROM :tableRef WHERE :key = :value
     *
     * @param string         $table_ref Table reference
     * @param string|int     $value     The ID of the row to select.
     * @param string         $key       Key's reference
     * @param DBQueryBuilder $builder   DBQueryBuilder used to load data from the database. A new instance will be created if not specified.
     * @param int            $options   Options
     *
     * @return stdClass|bool ActiveRecord object, or FALSE if not round
     */
    public static function getOneByKey(string $table_ref, $value, string $key, ?DBQueryBuilder $builder = NULL, int $options = 0) {
        return static::_getByKey($table_ref, $value, $key, $options | DB::OPT_GET_ONE, $builder);
    }

    /**
     * Helper method to SELECT * FROM :tableRef WHERE :key IN (:value)
     *
     * @param string           $table_ref   Table reference
     * @param string|int|array $value       The ID of the row(s) to select. Specify an array to select multiple rows.
     * @param string           $key         Key's reference
     * @param string|null      $index_field If specified, returned array will use this field as indices.
     * @param int              $options     Options
     *
     * @return array Array of ActiveRecord objects; can be empty.
     */
    public static function getManyByKey(string $table_ref, $value, string $key, ?string $index_field = NULL, int $options = 0) : array {
        return static::_getByKey($table_ref, $value, $key, $options | DB::OPT_GET_MANY, NULL, $index_field);
    }

    private static function _getByKey(string $table_ref, $value, string $key, int $options = 0, ?DBQueryBuilder $builder = NULL, ?string $index_field = NULL) {
        if (is_array($value) && empty($value)) {
            return [];
        }
        if (empty($builder)) {
            $builder = new self();
        }
        $builder->select("t.*")->from($table_ref, 't');
        $class_type = static::_getClassTypeForTable($table_ref);
        if (is_array($value)) {
            $builder->where("t.$key IN (:ids)", $value);
        } else {
            $builder->where("t.$key", $value);
        }
        if (options_contains($options, DB::GET_OPT_CACHED)) {
            $builder->cached();
        }
        if (options_contains($options, DB::OPT_GET_MANY)) {
            $results = $builder->getAll($index_field, $options, $class_type);
        } else {
            $results = $builder->getFirst($class_type);
        }
        if (!empty($results) && options_contains($options, DB::GET_OPT_CACHED)) {
            $o = first(to_array($results));
            if ($o instanceof AbstractActiveRecord) {
                $o::cache($results);
            }
        }
        return $results;
    }

    private static $_active_record_classes = [];
    private static function _getClassTypeForTable(string $table_ref) : ?string {
        if (empty(static::$_active_record_classes)) {
            // Load and cache the implemented AbstractActiveRecord classes
            // Note: we use glob() instead of get_declared_classes() to find all the possible ActiveRecord classes, because we use an autoloader, which means maybe not all ActiveRecord classes have been autoloaded yet
            foreach (glob("classes/ActiveRecord/*.php") as $class_file) {
                $filename = basename($class_file);
                $class_name = explode(".", $filename);
                $class_type = "Netlift\\ActiveRecord\\" . $class_name[0];
                if (is_subclass_of($class_type, AbstractActiveRecord::class)) {
                    static::$_active_record_classes[$class_type::TABLE_NAME] = $class_type;
                }
            }
        }
        return @static::$_active_record_classes[$table_ref];
    }

    public function getDebug() {
        $qs = [];
        foreach ($this->getParams() as $k => $v) {
            if ($v === NULL) {
                $qs[] = "SET @$k = NULL";
            } elseif ($v === FALSE) {
                $qs[] = "SET @$k = 0";
            } elseif ($v === TRUE) {
                $qs[] = "SET @$k = 1";
            } else {
                $qs[] = "SET @$k = '" . str_replace("'", "''", $v) . "'";
            }
        }
        $qs[] = str_replace(':', '@', $this->build());
        return implode(";\n", $qs);
    }

    private static function _escapeColumnName(string $col_came) : string {
        if (preg_match('/^([a-z0-9_]+)$/i', $col_came, $re)) {
            return '`' . $re[1] . '`';
        }
        if (preg_match('/^([a-z0-9_]+) ([a-z0-9_]+)$/i', $col_came, $re)) {
            return '`' . $re[1] . '` ' . $re[2];
        }
        return $col_came;
    }

    private function _addParam(string &$name, $value, ?string &$where_condition = NULL) {
        if (is_array($value)) {
            $in_query = [];
            $i = 0;
            foreach ($value as $el) {
                $el_name = "{$name}_{$i}";
                $this->_addParam($el_name, $el);
                $in_query[] = ":$el_name";
                $i++;
            }
            $where_condition = str_replace(":$name", implode(', ', $in_query), $where_condition);
            return;
        }

        $new_name = $name;
        $i = 1;
        while (isset($this->_params[$new_name])) {
            $new_name = $name . "_" . $i++;
        }
        $name = $new_name;
        $this->_params[$new_name] = $value;
    }

    private function _applyParams(&$where_condition, $param_values) {
        if (!empty($param_values)) {
            if (preg_match_all('/:([a-z0-9_]+)/i', $where_condition, $re)) {
                $i = 0;
                $re[1] = array_values(array_unique($re[1]));
                if (count($re[1]) != count($param_values)) {
                    die("Error: number of arguments is different from number of placeholders");
                }
                foreach ($param_values as $param_value) {
                    $this->_addParam($re[1][$i++], $param_value, $where_condition);
                }
            } elseif (count($param_values) == 1) {
                if ($param_values[0] === NULL) {
                    $where_condition = static::_escapeColumnName($where_condition) . " IS NULL";
                } else {
                    if (preg_match('/^[a-z0-9_]+\.([a-z0-9_]+)$/i', $where_condition, $re) || preg_match('/^([a-z0-9_]+)$/i', $where_condition, $re)) {
                        $this->_addParam($re[1], $param_values[0]);
                        $where_condition = static::_escapeColumnName($where_condition) . " = :" . $re[1];
                    } else {
                        die("Error: can't find where to inject param value in '$where_condition'.");
                    }
                }
            } else {
                die("Error: placeholders params needed when specifying multiple param values.");
            }
        }
    }
}
