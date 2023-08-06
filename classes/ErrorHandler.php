<?php
namespace PommePause\Dropinambour;

use Throwable;

class ErrorHandler
{
    private static array $_errorType = array (
        E_ERROR             => 'ERROR',
        E_WARNING           => 'WARNING',
        E_PARSE             => 'PARSING ERROR',
        E_NOTICE            => 'NOTICE',
        E_CORE_ERROR        => 'CORE ERROR',
        E_CORE_WARNING      => 'CORE WARNING',
        E_COMPILE_ERROR     => 'COMPILE ERROR',
        E_COMPILE_WARNING   => 'COMPILE WARNING',
        E_USER_ERROR        => 'USER ERROR',
        E_USER_WARNING      => 'USER WARNING',
        E_USER_NOTICE       => 'USER NOTICE',
        E_STRICT            => 'STRICT NOTICE',
        E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
        E_DEPRECATED        => 'DEPRECATION WARNING',
        E_USER_DEPRECATED   => 'USER DEPRECATION WARNING',
    );

    public static function handler($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }
        $log = static::$_errorType[$errno] . " $errstr\nStack trace:\n#0  " . $errfile . "($errline)\n" . static::getStackTrace();
        echo $log;
        Logger::error($log);
    }

    public static function exHandler(Throwable $ex) {
        $log = "Uncaught Exception: " . $ex->getMessage() . "\nStack trace:\n#0  " . $ex->getFile() . "(" . $ex->getLine() . ")\n" . static::getStackTrace($ex->getTrace());
        echo $log;
        Logger::error($log);
    }

    public static function getStackTrace(array $backtrace = []) : string {
        // start backtrace
        $trace = '';
        $level = 1;
        if (empty($backtrace)) {
            $backtrace = debug_backtrace();
            array_shift($backtrace);
            array_shift($backtrace);
        }
        $extras = [];
        foreach ($backtrace as $v) {
            $args = [];
            if (!empty($v['args']) && (!isset($v['class']) || !string_contains($v['class'], 'Encryption'))) {
                foreach ($v['args'] as $arg) {
                    $args[] = static::_getArgument($arg);
                }
            }
            $trace .= "#$level  ";
            if (!empty($v['file'])) {
                $trace .= $v['file'];
            }
            if (!empty($v['line'])) {
                $trace .= "({$v['line']})";
            }
            $trace .= ": ";
            $args_as_text = implode(', ', $args);
            if (strlen($args_as_text) > 64) {
                $line_prefix = $trace;
            } else {
                $line_prefix = FALSE;
            }
            if (!empty($v['class'])) {
                if ($line_prefix) {
                    $trace .= $v['class'] . $v['type'] . $v['function'] . '(...)';
                    $extras[] = "#$level  ($args_as_text)";
                } else {
                    $trace .= $v['class'] . $v['type'] . $v['function'] . "($args_as_text)";
                }
            } elseif (!empty($v['function']) && empty($trace)) {
                if ($line_prefix) {
                    $trace .= $v['function'] . '(...)';
                    $extras[] = "#$level  ($args_as_text)";
                } else {
                    $trace .= $v['function'] . "($args_as_text)";
                }
            }
            $trace .= "\n";
            $level++;
        }
        if (!empty($extras)) {
            $trace .= "Full arguments details:\n";
            foreach ($extras as $extra) {
                $trace .= "$extra\n";
            }
        }
        return $trace;
    }

    private static function _getArgument($arg) : string {
        switch (strtolower(gettype($arg))) {
        case 'string':
            return ( '"' . str_replace(array("\n"), array(''), $arg) . '"');
        case 'boolean':
            return (bool) $arg;
        case 'object':
            $json = @json_encode($arg);
            if ($json) {
                return $json;
            }
            return 'object(' . get_class($arg) . (isset($arg->id) ? "@id=$arg->id" : '') . ')';
        case 'array':
            $json = @json_encode($arg);
            if ($json) {
                return $json;
            }
            $ret = array();
            foreach ($arg as $k => $v) {
                $ret[] = static::_getArgument($k).' => ' . static::_getArgument($v);
            }
            return 'array(' . implode(', ', $ret) . ')';
        case 'resource':
            return 'resource('.get_resource_type($arg).')';
        default:
            return var_export($arg, TRUE);
        }
    }
}
