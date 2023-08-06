<?php
namespace PommePause\Dropinambour;

use Exception;

ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 1);
ini_set("log_errors", 1);

require 'vendor/autoload.php';
require 'functions.inc.php';

try {
    DB::connect();
} catch (Exception $ex) {
    die($ex->getMessage());
}

set_error_handler(['PommePause\Dropinambour\ErrorHandler', 'handler']);
set_exception_handler(['PommePause\Dropinambour\ErrorHandler', 'exHandler']);

$is_http_request = !empty($_SERVER['REQUEST_METHOD']);

if ($is_http_request) {
    // Ref: https://labs.detectify.com/2020/08/13/modern-php-security-part-1-bug-classes/, https://github.com/magento/magento2/commit/eb820e097c2e84241748aebd6d1bec6ca61f2b9d
    @stream_wrapper_unregister('phar');

    // Starts the session
    new MySessionHandler();
}
