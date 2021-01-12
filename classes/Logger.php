<?php

namespace PommePause\Dropinambour;

use DateTime;
use DateTimeZone;

class Logger
{
    public static function debug($log) : void {
        static::log('DEBUG', $log);
    }

    public static function info($log) : void {
        static::log('INFO', $log);
    }

    public static function warning($log) : void {
        static::log('WARN', $log);
    }

    public static function error($log) : void {
        static::log('ERROR', $log);
    }

    public static function critical($log) : void {
        static::log('CRITICAL', $log);
        exit(1);
    }

    private static function log(string $level, string $log) : void {
        $log_levels = ['DEBUG', 'INFO', 'WARN', 'ERROR'];
        $this_log_level = array_search($level, $log_levels);
        $threshold_log_level = array_search(Config::get('LOG_LEVEL', 'INFO'), $log_levels);
        if ($this_log_level < $threshold_log_level) {
            return;
        }

        // Hide Plex tokens from logs
        $log = preg_replace('/X-Plex-Token=.+\)/U', 'X-Plex-Token=[hidden])', $log);

        /** @noinspection PhpUnhandledExceptionInspection */
        $date = new DateTime('now', new DateTimeZone(Config::get('SYSTEM_TIMEZONE')));
        $datetime = $date->format('Y-m-d H:i:s T');

        $log_id = static::getRequestID($datetime);

        if (!isset($_SERVER['SERVER_PROTOCOL'])) {
            // Command line
            global $log_source;
            if (empty($log_source)) {
                $log_source = 'cli';
            }
            if (strlen($log_id) > 7) {
                $log_id = substr($log_id, 0, 7);
            }
            $prefix = "[source $log_source] [date $datetime] [req $log_id]";
        } else {
            // HTTP request
            $ip = static::getClientIP();

            $method_details = "";
            if (!empty($_SERVER['REQUEST_METHOD']) && @$log[0] == '/') {
                $method_details = " [method " . $_SERVER['REQUEST_METHOD'] . "]";
            }

            $prefix = "";
            $prefix .= "[source web] ";
            $prefix .= "[client $ip] ";
            $prefix .= "[date $datetime] [req $log_id]{$method_details}";
        }
        error_log("[$level] $prefix $log\n", 3, static::getLogFile());
    }

    private static function getLogFile() : string {
        return Config::get('LOG_FILE', '/tmp/dropinambour.log');
    }

    private static function getClientIP() : string {
        $ip = '-';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['FORWARDED_FOR'])) {
            $ip = $_SERVER['FORWARDED_FOR'];
        } elseif (isset($_SERVER['X_FORWARDED_FOR'])) {
            $ip = $_SERVER['X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['X_HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['X_HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (string_contains($ip, ', ')) {
            $ips = explode(', ', $ip);
            foreach ($ips as $ip) {
                if (!string_contains($ip, ':')) {
                    break;
                }
            }
        }
        return $ip;
    }

    private static function getRequestID(string $datetime) : string {
        global $log_id;
        if (empty($log_id)) {
            if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
                $log_id = $_SERVER['HTTP_X_REQUEST_ID'];
            } else {
                if (empty($datetime)) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $date = new DateTime('now', new DateTimeZone(Config::get('SYSTEM_TIMEZONE')));
                    $datetime = $date->format('Y-m-d H:i:s T');
                }
                $log_id = md5($datetime . rand(0, 999999));
            }
        }
        return $log_id;
    }
}
