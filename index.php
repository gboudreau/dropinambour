<?php
namespace PommePause\Dropinambour;

use Symfony\Component\HttpFoundation\Request;

chdir(__DIR__);
require_once 'init.inc.php';

// Instantiate the controller
$request = Request::createFromGlobals();
$controller = new AppController();

// Log the beginning of the HTTP request
$controller->log();

// Call the requested controller method
$response = $controller->route($request);

// Send the response to the client (browser)
$response->send();

// Log the end of the HTTP request
$controller->log($response);

exit(0);
