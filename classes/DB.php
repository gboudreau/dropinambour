<?php

namespace PommePause\Dropinambour;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use PommePause\Dropinambour\ActiveRecord\AbstractActiveRecord;

class DB
{
    private const QUERY_OPT_DO_NOT_RETRY = 0b000001; // Don't retry query, if query fails
    public  const QUERY_OPT_NO_STATS     = 0b000010; // Don't log DB stats
    public  const GET_OPT_INDEXED_ARRAYS = 0b000100; // Return results as an array of arrays, using the specified field as indices
    public  const GET_OPT_CACHED         = 0b001000; // Return cached values, if available. Cache fetched values, if not.
    public  const OPT_GET_ONE            = 0b010000;
    public  const OPT_GET_MANY           = 0b100000;

    private const CONNECT_OPT_DO_NOT_RETRY = 0b01; // Don't retry connecting, if connection fails
    private const CONNECT_OPT_QUIET        = 0b10; // Don't send notifications when connect() fails

    // MySQL Error codes
    // Ref: http://dev.mysql.com/doc/refman/5.6/en/error-messages-server.html
    //      http://dev.mysql.com/doc/refman/5.6/en/error-messages-client.html
    private const MYSQL_ERROR_ER_LOCK_WAIT_TIMEOUT = 1205;
    private const MYSQL_ERROR_ER_LOCK_DEADLOCK = 1213;
    private const MYSQL_ERROR_ER_OPTION_PREVENTS_STATEMENT = 1290; // The MySQL server is running with the --read-only option so it cannot execute this statement
    private const MYSQL_ERROR_CR_CONNECTION_ERROR = 2002;
    private const MYSQL_ERROR_CR_CONN_HOST_ERROR = 2003;
    private const MYSQL_ERROR_CR_UNKNOWN_HOST = 2005;
    private const MYSQL_ERROR_CR_SERVER_GONE_ERROR = 2006;
    private const MYSQL_ERROR_CR_SERVER_LOST = 2013;
    private const MYSQL_ERROR_CR_SERVER_LOST_EXTENDED = 2055;

    /**
     * PDO handle used as the main database connection.
     *
     * @var PDO
     */
    private static $_handle;

    private static $_lockTimeoutRetries = 0;

    private static $_reconnectTimeoutRetries = 0;

    private static $_query_cache = [];

    private static function _isDebug() : bool {
        return ( defined('DEBUGSQL') && DEBUGSQL === TRUE );
    }

    public static function getPDO() : ?PDO {
        return static::$_handle;
    }

    /**
     * Connects to the database(s), and keep a handle ready to be used for further SQL queries.
     *
     * @param int $options Specify CONNECT_OPT_QUIET to NOT send notifications about connections failures.
     *
     * @return void
     * @throws Exception Thrown when we can't connect to the database.
     */
    public static function connect(int $options = 0) : void {
        if (!Config::get('DB_ENGINE') == 'mysql') {
            die("Error: Only MySQL connections are possible at this time.");
        }

        $handle = static::_connectMySQL(Config::get('DB_HOST'), Config::get('DB_PORT') ?? '3306', Config::get('DB_USER'), Config::get('DB_PWD'), Config::get('DB_NAME'), Config::get('DB_CA_CERT'), $options);
        static::$_handle = $handle;
    }

    /**
     * Create a PDO handle by connecting to the specified MySQL server.
     *
     * @param string      $host       Hostname to connect to. Make sure this matches the SSL certificate host, or connection will fail.
     * @param string      $port       Port to connect to.
     * @param string      $username   Username to authenticate with.
     * @param string      $password   Password to authenticate with.
     * @param string      $db_name    Database to use by default.
     * @param string|null $db_ca_cert Optional CA certificate file to use for SSL connection.
     * @param int         $options    Specify CONNECT_OPT_DO_NOT_RETRY to prevent an infinite loop of retries, when the connection fails. Specify CONNECT_OPT_QUIET to NOT send notifications about connections failures.
     *
     * @return PDO PDO handle for the connection.
     *
     * @throws Exception Thrown when we can't connect to the database.
     */
    private static function _connectMySQL(string $host, string $port, string $username, string $password, string $db_name, ?string $db_ca_cert = NULL, int $options = 0) : PDO {
        // Example connect string: 'mysql:host=localhost;port=9005;dbname=test'
        $connect_string = "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4";

        $opt = array(
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        if (!empty($db_ca_cert)) {
            if (file_exists($db_ca_cert)) {
                $opt[PDO::MYSQL_ATTR_SSL_CA] = $db_ca_cert;
                if (defined('\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    // Needed because we connect to a hostname that is a CNAME pointing to the correct hostname
                    // We could remove this if we connected to the RDS-provided hostnames
                    $opt[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = FALSE;
                }
            } else {
                Logger::error("Specified DB_CA_CERT file was not found on disk!");
            }
        }

        try {
            $handle = @new PDO($connect_string, $username, $password, $opt);

            // We don't need REPEATABLE-READ, which is the default, and will keep locks longer for no good reason.
            $stmt = $handle->prepare("SET tx_isolation = 'READ-COMMITTED'");
            $stmt->execute();
            $stmt = $handle->prepare("SET sql_mode = ''");
            $stmt->execute();

            $handle->host = $host;

            return $handle;
        } catch (PDOException $e) {
            // Connect failed; should we retry?
            if (!options_contains($options, static::CONNECT_OPT_DO_NOT_RETRY)) {
                $retry_able_errors = [static::MYSQL_ERROR_CR_CONNECTION_ERROR, static::MYSQL_ERROR_CR_CONN_HOST_ERROR, static::MYSQL_ERROR_CR_UNKNOWN_HOST];
                if (array_contains($retry_able_errors, $e->getCode())) {
                    // 'php_network_getaddresses: getaddrinfo failed: Name or service not known', AKA DNS resolution failed

                    $log = "Can't connect to the database (host = $host). Will retry after 1 second. Error: [" . $e->getCode() . "] " . $e->getMessage();
                    static::_logError($log);

                    // Let's retry after 1 second
                    sleep(1);
                    return static::_connectMySQL($host, $port, $username, $password, $db_name, $db_ca_cert, $options | static::CONNECT_OPT_DO_NOT_RETRY);
                }
            }

            $log = "Failed to connect to the database (host = $host). Error: [" . $e->getCode() . "] " . $e->getMessage() . ($e->getPrevious() ? ", caused by: " . $e->getPrevious()->getMessage() : '');
            static::_logError($log);

            throw new Exception($log);
        }
    }

    /**
     * Disconnect from the database(s) by deleting the PDO handle(s) we created in connect()
     *
     * @return void
     */
    public static function disconnect() : void {
        static::$_handle = NULL;
    }

    /**
     * Choose the correct PDO handle to run a query. Will choose either the master, or a slave, depending on the query, and on the values in $_forceMaster.
     *
     * @param string $q The SQL query.
     *
     * @return PDO
     */
    private static function _getHandleForQuery(string $q) : PDO {
        $handle = static::$_handle;
        if (!($handle instanceof PDO)) {
            static::_logError("static::\$_handle is " . gettype($handle));
            throw new Exception("Failed to get a valid PDO handle to work with. Got a " . gettype($handle), static::MYSQL_ERROR_CR_CONNECTION_ERROR);
        }
        return $handle;
    }

    public static function execute(string $q, $args = [], int $options = 0) : PDOStatement {
        $handle = static::_getHandleForQuery($q);

        // Handle array arguments; eg. $q = "[...] WHERE id IN (:users)", $args = ['users' => array([...])]
        if (is_array($args)) {
            foreach ($args as $k => $v) {
                if (is_array($v)) {
                    unset($args[$k]);
                    $inQuery = [];
                    $i = 0;
                    foreach ($v as $el) {
                        $inQuery[] = ":$k" . "_$i";
                        $args["$k" . "_$i"] = $el;
                        $i++;
                    }
                    $q = str_replace(":$k", implode(', ', $inQuery), $q);
                }
            }
        }

        $stmt = $handle->prepare($q);

        if (isset($stats_query_index) && $stats_query_index >= 0) {
            $stmt->stats_query_index = $stats_query_index;
        }

        if (!is_array($args)) {
            // If there is only one argument; no need to use an array for $args
            if (preg_match('/:([a-z0-9_]+)/', $q, $re)) {
                $args = array($re[1] => $args);
            }
        }
        // Bind arguments
        foreach ($args as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        if (static::_isDebug()) {
            $ms = explode('.', microtime(TRUE));
            $date_prefix = "[" . date('Y-m-d H:i:s') . ".".round($ms[1]/10) . "]";
            $date_prefix2 = microtime(TRUE);
            $qd = str_replace(':', '@', $q);
            $params = [];
            foreach ($args as $k => $v) {
                $params[] = "SET @$k = '$v';";
            }
            $params = implode(' ', $params);
            $SEP = " ";
            echo $date_prefix . $SEP . $date_prefix2 . $SEP . $params . $SEP . $qd . "\n";
        }
        try {
            @$stmt->execute();

            // Reset timeouts
            static::$_lockTimeoutRetries = NULL;
            static::$_reconnectTimeoutRetries = NULL;

            return $stmt;
        } catch (PDOException $e) {
            $error_info = $stmt->errorInfo();
            if ($error_info[1] == NULL) {
                // PDO (not MySQL) error
                $error_info = array($e->getCode(), $e->getCode(), $e->getMessage());
            }
            $error_code = (int) $error_info[1];

            // Query failed; should we retry?
            if (!options_contains($options, static::QUERY_OPT_DO_NOT_RETRY)) {
                $sleep_before_retry = 0;
                $reconnect = FALSE;
                $can_retry_again = FALSE;

                $lock_errors = [static::MYSQL_ERROR_ER_LOCK_WAIT_TIMEOUT, static::MYSQL_ERROR_ER_LOCK_DEADLOCK];
                if (array_contains($lock_errors, $error_code)) {
                    $sleep_before_retry = 1;

                    // Retry 'lock wait timeout' errors for 10 minutes, then give up
                    if (empty(static::$_lockTimeoutRetries)) {
                        static::$_lockTimeoutRetries = time();
                    }
                    $can_retry_again = time() < static::$_lockTimeoutRetries + 10*60;
                }

                $disconnected_errors = [static::MYSQL_ERROR_CR_SERVER_GONE_ERROR, static::MYSQL_ERROR_CR_SERVER_LOST, static::MYSQL_ERROR_CR_SERVER_LOST_EXTENDED, static::MYSQL_ERROR_ER_OPTION_PREVENTS_STATEMENT];
                if (array_contains($disconnected_errors, $error_code)) {
                    // Try to re-connect to server before retrying the query
                    $reconnect = TRUE;

                    if (empty(static::$_reconnectTimeoutRetries)) {
                        static::$_reconnectTimeoutRetries = time();
                    }

                    if (isset($_SERVER['HTTP_HOST'])) {
                        // HTTP request; 1s between retries; give up after 1m
                        $sleep_before_retry = 1;
                        $can_retry_again = time() < static::$_reconnectTimeoutRetries + 1*60;
                    } else {
                        // Worker thread; 1s between retries, give up after 5m
                        $sleep_before_retry = 1;
                        $can_retry_again = time() < static::$_reconnectTimeoutRetries + 5*60;
                    }
                }

                if ($sleep_before_retry > 0) {
                    sleep($sleep_before_retry);

                    if ($reconnect) {
                        try {
                            static::connect(static::CONNECT_OPT_QUIET);
                            //static::_logError("Successfully re-connected.");
                        } catch (Exception $ex) {
                            // pass
                            static::_logError("Caught exception when re-connecting after PDOException($error_code, $error_info[2]): " . $ex->getMessage());
                        }
                    }

                    $options = $options | ( $can_retry_again ? 0 : static::QUERY_OPT_DO_NOT_RETRY );
                    return static::execute($q, $args, $options);
                }
            }

            // Reset timeouts
            static::$_lockTimeoutRetries = NULL;
            static::$_reconnectTimeoutRetries = NULL;

            $error_message = "Failed to execute query: $q; error: [$error_code] " . $error_info[2];
            throw new Exception($error_message, $error_code);
        }
    }

    private static function _logError($log) {
        Logger::error("Database Error: $log");
    }

    public static function insert(string $q, $args = []) {
        static::execute($q, $args);
        return static::lastInsertedId();
    }

    public static function lastInsertedId() {
        if (Config::get('DB_ENGINE') == 'mysql') {
            return (int) static::$_handle->lastInsertId();
        }
        return TRUE;
    }

    private static function limitCacheSize() : void {
        $max_cache_size = Config::get('MAX_DB_QUERY_CACHE_SIZE', 8 * 1024*1024);
        while (count(static::$_query_cache) > 1) {
            $cache_size = mb_strlen(serialize(static::$_query_cache), '8bit');
            if ($cache_size < $max_cache_size) {
                return;
            }
            array_shift(static::$_query_cache);
        }
    }

    public static function getFirst(string $q, $args = [], int $options = 0, ?string $class_type = NULL) {
        if (options_contains($options, static::GET_OPT_CACHED)) {
            $cache_key = static::_getQueryCacheKey($q, $args, NULL, $options);
            if (isset(static::$_query_cache[$cache_key])) {
                return static::$_query_cache[$cache_key];
            }
        }
        $stmt = static::execute($q, $args, $options);
        if (!empty($class_type)) {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $class_type);
        } else {
            $stmt->setFetchMode(PDO::FETCH_OBJ);
        }
        $result = $stmt->fetch();
        if (!empty($class_type) && $result instanceof AbstractActiveRecord) {
            $result->unbox();
        }
        if (isset($cache_key)) {
            static::$_query_cache[$cache_key] = $result;
            static::limitCacheSize();
        }
        return $result;
    }

    public static function getFirstValue(string $q, $args = [], int $options = 0) {
        if (options_contains($options, static::GET_OPT_CACHED)) {
            $cache_key = static::_getQueryCacheKey($q, $args, NULL, $options);
            if (isset(static::$_query_cache[$cache_key])) {
                return static::$_query_cache[$cache_key];
            }
        }
        $stmt = static::execute($q, $args, $options);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $value = FALSE;
        } else {
            $value = array_shift($row);
        }
        if (isset($cache_key)) {
            static::$_query_cache[$cache_key] = $value;
            static::limitCacheSize();
        }
        return $value;
    }

    public static function getAll(string $q, $args = [], ?string $index_field = NULL, int $options = 0, ?string $class_type = NULL) : array {
        $cache_key = static::_getQueryCacheKey($q, $args, $index_field, $options);
        if (isset(static::$_query_cache[$cache_key])) {
            if (options_contains($options, static::GET_OPT_CACHED)) {
                return array_clone(static::$_query_cache[$cache_key]);
            } else {
                // Run query, to update cache
                $options |= static::GET_OPT_CACHED;
            }
        }
        $stmt = static::execute($q, $args, $options);
        $rows = [];
        $i = 0;
        if (!empty($class_type)) {
            $stmt->setFetchMode(PDO::FETCH_CLASS, $class_type);
        } else {
            $stmt->setFetchMode(PDO::FETCH_OBJ);
        }
        while ($row = $stmt->fetch()) {
            $index = $i++;
            if (!empty($index_field)) {
                $index = $row->{$index_field};
            }
            if (options_contains($options, static::GET_OPT_INDEXED_ARRAYS)) {
                $rows[$index][] = $row;
            } else {
                $rows[$index] = $row;
            }
            if (!empty($class_type) && $row instanceof AbstractActiveRecord) {
                $row->unbox();
            }
        }
        if (options_contains($options, static::GET_OPT_CACHED)) {
            static::$_query_cache[$cache_key] = array_clone($rows);
            static::limitCacheSize();
        }
        return $rows;
    }

    public static function getAllValues(string $q, $args = [], ?string $data_type = NULL, int $options = 0) : array {
        if (options_contains($options, static::GET_OPT_CACHED)) {
            $cache_key = static::_getQueryCacheKey($q, $args, NULL, $options);
            if (isset(static::$_query_cache[$cache_key])) {
                return static::$_query_cache[$cache_key];
            }
        }
        $stmt = static::execute($q, $args, $options);
        $values = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = array_shift($row);
            if (!empty($data_type)) {
                settype($value, $data_type);
            }
            $values[] = $value;
        }
        if (isset($cache_key)) {
            static::$_query_cache[$cache_key] = $values;
            static::limitCacheSize();
        }
        return $values;
    }

    private static function _getQueryCacheKey(string $q, $args = [], ?string $index_field = NULL, int $options = 0) : string {
        if (options_contains($options, static::GET_OPT_CACHED)) {
            $options -= static::GET_OPT_CACHED;
        }
        return md5($q . json_encode($args) . ($index_field ?? '') . $options);
    }

    public static function emptyCache() : void {
        static::$_query_cache = [];
    }

    public static function setTypesOnArray(array &$array, array $types) : void {
        global $_types;
        $_types = $types;
        $array = array_map([get_called_class(), 'setTypes'], $array);
    }

    public static function setTypes(&$el, ?array $types = NULL) {
        if (empty($types)) {
            global $_types;
            $types = $_types;
        }
        if (is_array($el)) {
            $el = (object) $el;
        }
        foreach ($types as $prop => $type) {
            if (@$el->{$prop} === NULL) {
                continue;
            }
            switch ($type) {
            case 'unset':
                unset($el->{$prop});
                break;
            case 'int':
            case 'integer':
                $el->{$prop} = (int) $el->{$prop};
                break;
            case 'float':
            case 'double':
                $el->{$prop} = (float) $el->{$prop};
                break;
            case 'bool':
            case 'boolean':
                $el->{$prop} = ( $el->{$prop} === TRUE || $el->{$prop} == 1 || strtolower($el->{$prop}) == 'true' || strtolower($el->{$prop}) == 'yes' );
                break;
            case 'json':
                if (is_string($el->{$prop})) {
                    $el->{$prop} = json_decode($el->{$prop});
                }
                break;
            case 'json_as_arrays':
                if (is_string($el->{$prop})) {
                    $el->{$prop} = json_decode($el->{$prop}, TRUE);
                }
                break;
            case 'array_from_string':
                if (is_string($el->{$prop})) {
                    $el->{$prop} = explode(',', $el->{$prop});
                }
                break;
            }
        }
        return $el;
    }

    public static function startTransaction() : void {
        static::execute("SET autocommit=0", [], static::QUERY_OPT_NO_STATS);
    }

    public static function commitTransaction() : void {
        static::execute("COMMIT", [], static::QUERY_OPT_NO_STATS);
        static::execute("SET autocommit=1", [], static::QUERY_OPT_NO_STATS);
    }

    public static function rollbackTransaction() : void {
        static::execute("ROLLBACK", [], static::QUERY_OPT_NO_STATS);
        static::execute("SET autocommit=1", [], static::QUERY_OPT_NO_STATS);
    }
}
