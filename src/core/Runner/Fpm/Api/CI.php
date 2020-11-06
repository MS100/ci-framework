<?php

namespace CI\core\Runner\Fpm\Api;

use CI\core\Controller;
use CI\core\Input;
use CI\core\Output\Format\Json;

class CI extends \CI\core\CI
{
    protected const CONFIG_TYPE_NAME = 'api';
    protected const ALLOW_RESPONSE_FORMAT = [
        Json::class,
    ];

    final public function request()
    {
        try {
            $this->input();
            $this->init();

            $e404 = false;
            $class_name = $this->router->fetch_class_name();
            $method = $this->router->method;

            if (empty($this->router->class)) {
                $e404 = true;
            } else {
                if (!class_exists($class_name) || $method[0] === '_' || method_exists(Controller::class, $method)) {
                    $e404 = true;
                } elseif (!method_exists($class_name, $method)) {
                    $e404 = true;
                } /**
                 * DO NOT CHANGE THIS, NOTHING ELSE WORKS!
                 * - method_exists() returns true for non-public methods, which passes the previous elseif
                 * - is_callable() returns false for PHP 4-style constructors, even if there's a __construct()
                 * - method_exists($class, '__construct') won't work because \CI\core\CI::__construct() is inherited
                 * - People will only complain if this doesn't work, even though it is documented that it shouldn't.
                 * ReflectionMethod::isConstructor() is the ONLY reliable check,
                 * knowing which method will be executed as a constructor.
                 */
                elseif (!is_callable([$class_name, $method])) {
                    $reflection = new \ReflectionMethod($class_name, $method);
                    if (!$reflection->isPublic() OR $reflection->isConstructor()) {
                        $e404 = true;
                    }
                }
            }

            if ($e404) {
                show_404($this->uri->uri_string);
            }

            $CTL = new $class_name();

            $results = $CTL->$method();
        } catch (\Throwable $e) {
            $results = dispose_exception($e);
        }

        $this->output($results);
    }

    final private function input()
    {
        if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
            $this->output->setMakeStructureCallback(
                function ($response) {
                    return [
                        'header' => $this->input instanceof Input ? $this->input->get_request_info() : [],
                        'response' => $response
                    ];
                }
            );

            $input_stream = file_get_contents('php://input');

            if (empty($input_stream) || $input_stream[0] !== '{' || $input_stream[strlen($input_stream) - 1] !== '}') {
                show_error('请求JSON数据格式有误', 403);
            }

            $input = json_decode($input_stream, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                show_error('请求JSON数据格式有误', 403);
            }

            $_SERVER['SERVER_ADDR'] = $input['header']['local_ip'] ?? null;
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $input['header']['user_ip'] ?? null;
            $_SERVER['REMOTE_ADDR'] = $input['header']['client_ip'] ?? null;
            $_SERVER['REQUEST_PRODUCT_LOG_ID'] = $input['header']['log_id'] ?? null;
            $_SERVER['REQUEST_PRODUCT_NAME'] = $input['header']['product_name'] ?? null;


            if (!isset($input['request'], $input['request']['c'], $input['request']['m'])) {
                show_error('请求数据request字段格式错误', 403);
            }

            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['argv'] = ['index.php', $input['request']['c'], $input['request']['m']];
            //$_SERVER['PATH_INFO'] = strtolower($input['request']['c'] . '/' . $input['request']['m']);
            //$_SERVER['REQUEST_URI'] = strtolower($input['request']['c'] . '/' . $input['request']['m']);
            //$_SERVER['PHP_SELF'] = 'index.php';

            $_REQUEST = $_POST = !empty($input['request']['p']) && is_array($input['request']['p']) ? $input['request']['p'] : [];
        } else {
            $_SERVER['argv'] = ['index.php', $_SERVER['PATH_INFO']];
        }
    }

    final protected function output($data)
    {
        echo parent::output($data);
    }
}
