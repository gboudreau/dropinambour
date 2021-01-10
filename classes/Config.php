<?php

namespace PommePause\Dropinambour;

class Config
{
    public const GET_OPT_PARSE_AS_JSON = 0b1;

    public static function get($name, $default = FALSE, int $options = 0) {
        $env_value = getenv($name);
        if ($env_value !== FALSE) {
            $value = $env_value;
        }
        if (!isset($value)) {
            global $CONFIG;
            if (empty($CONFIG)) {
                require_once __DIR__ . '/../_config/config.php';
            }
            if (isset($CONFIG->{$name})) {
                $value = $CONFIG->{$name};
            } else {
                $value = $default;
            }
        }
        return static::parseValue($value, $options);
    }

    public static function set($name, $value) {
        global $CONFIG;
        $CONFIG->{$name} = $value;
        if (!is_string($value)) {
            $value = json_encode($value);
        }
        putenv("$name=$value");
    }

    public static function getFromDB($name, $default = FALSE, int $options = 0) {
        $q = "SELECT c.value FROM config c WHERE c.key = :key";
        $value = DB::getFirstValue($q, $name, DB::GET_OPT_CACHED);
        if ($value === FALSE) {
            $value = $default;
        }
        return static::parseValue($value, $options);
    }

    public static function setInDB($name, $value) : void {
        if (!is_string($value)) {
            $value = json_encode($value);
        }
        $q = "INSERT INTO config SET `key` = :key, `value` = :value ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        DB::insert($q, ['key' => $name, 'value' => $value]);
    }

    private static function parseValue($value, $options) {
        if (is_string($value)) {
            if (strtoupper($value) === 'TRUE') {
                $value = TRUE;
            } elseif (strtoupper($value) === 'FALSE') {
                $value = FALSE;
            } elseif (strtoupper($value) === 'NULL') {
                $value = NULL;
            } elseif (options_contains($options, static::GET_OPT_PARSE_AS_JSON)) {
                $value = json_decode($value);
            }
        }
        return $value;
    }
}

date_default_timezone_set(Config::get('SYSTEM_TIMEZONE'));
