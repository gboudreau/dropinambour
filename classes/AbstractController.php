<?php

namespace PommePause\Dropinambour;

use League\Plates\Engine;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractController
{
    private $_request;
    private $_response;

    /** @var Engine */
    private $_engine;

    private static $requestStart;

    public function __construct() {
        $templates_directory = './views';
        $this->_engine = new Engine($templates_directory);
        $this->_engine->addFolder('/emails', $templates_directory . '/emails');
        $this->_engine->addFolder('/admin', $templates_directory . '/admin');
        return $this->_engine;
    }

    public function route(Request $request) : Response {
        $this->_request = $request;
        // Which AppController method to call depends on the query parameters; see Router::getRouteForRequest()
        $method = Router::getRouteForRequest($this->_request);
        return $this->$method();
    }

    public function log(?Response $response = NULL) {
        // Log all HTTP requests to Heroku log (error_log)
        if (!empty($response)) {
            $time = microtime(TRUE)*1000 - static::$requestStart*1000;
            $http_code_number = $response->getStatusCode();
            $size = strlen($response->getContent());
            Logger::info("[req_time " . round($time) . "] [http_result $http_code_number] [req_size $size]");
        } else {
            static::$requestStart = microtime(TRUE);
            $uri = $_SERVER['REQUEST_URI'];
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $post = $_POST;
                $params = http_build_query($post);
                $sep = string_contains($uri, '?') ? '&' : '?';
                $uri .= $sep . $params;
            }
            Logger::info($uri);
        }
    }

    protected function render(string $template_date, array $data = array()) : string {
        return $this->_engine->render($template_date, $data);
    }

    protected function addData(array $data, $templates = NULL) : Engine {
        return $this->_engine->addData($data, $templates);
    }

    protected function error(string $http_code, string $error_message) : void {
        $http_code_number = (int) preg_replace('/([0-9]+) .*/', '\\1', $http_code);
        header($_SERVER['SERVER_PROTOCOL'] . " $http_code", TRUE, $http_code_number);
        echo $error_message;
        exit(0);
    }

    protected function response($content = '', int $status = 200, array $headers = array()) : Response {
        if (empty($headers['Content-type'])) {
            $headers['Content-type'] = 'text/html; charset=utf-8';
        }
        if (empty($this->_response)) {
            $this->_response = new Response($content, $status, $headers);
        }
        return $this->_response;
    }

    protected function request() : Request {
        return $this->_request;
    }

    protected function getParamFromRequest($key, $default = NULL) {
        return $this->request()->request->get($key, $default);
    }

    protected function getQueryParam($key, $default = NULL) {
        return $this->request()->query->get($key, $default);
    }

    protected function redirectResponse(string $url) : RedirectResponse {
        return new RedirectResponse($url);
    }

    protected function requestedJSONResponse() : bool {
        return array_contains(get_http_accepts(), 'application/json');
    }

    protected function jsonResponse($content, $status = Response::HTTP_OK) : Response {
        return $this->response(json_encode($content), $status, ['Content-type' => 'application/json; charset=utf-8']);
    }

    protected function jsonErrorResponse($error_description, int $http_code = Response::HTTP_BAD_REQUEST) : Response {
        return $this->response(json_encode(['error_description' => $error_description]), $http_code, ['Content-type' => 'application/json']);
    }

    protected function downloadResponse($data, $content_type, $filename) : Response {
        $headers = [
            'Content-type' => $content_type,
            'Content-Disposition' => "attachment; filename=$filename"
        ];
        return $this->response($data, Response::HTTP_OK, $headers);
    }
}
