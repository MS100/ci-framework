<?php

namespace CI\core\Runner\Swoole\Tcp;

use CI\core\Controller;
use CI\core\Output\Format\Json;

class CI extends \CI\core\CI
{
    protected const CONFIG_TYPE_NAME = 'tcp';
    protected const ALLOW_RESPONSE_FORMAT = [
        Json::class,
    ];

    /**
     * run
     *
     * @param  $request
     *
     * @access public
     * @return mixed
     */
    final public function request($request)
    {
        try {
            $this->input($request);
            $this->init();

            //log_message('debug', sprintf('input:%s', var_export($input, true)));

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
                $results = err('method_not_existed');
            } else {
                if (!isset($this->objects[$class_name])) {
                    $this->objects[$class_name] = new $class_name();
                }
                $results = $this->objects[$class_name]->$method();
            }
        } catch (\Throwable $e) {
            $results = dispose_exception($e);
        }


        return $this->output($response);
    }

    public function onWorkerStart(\Swoole\Server $serv, $worker_id)
    {
    }

    public function onTimer($timer_id, $param)
    {
    }

    public function onTask(\Swoole\Server $serv, $task_id, $from_id, $data)
    {

    }

    public function onFinish(\Swoole\Server $serv, $task_id, $data)
    {
        //echo "Task {$task_id} finish\n";
    }

    final private function input($input)
    {
        $this->flushResources();

        $_SERVER = [];

        $_SERVER['SERVER_ADDR'] = $input['header']['local_ip'] ?? null;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $input['header']['user_ip'] ?? null;
        $_SERVER['REMOTE_ADDR'] = $input['header']['client_ip'] ?? null;
        $_SERVER['REQUEST_PRODUCT_LOG_ID'] = $input['header']['log_id'] ?? null;

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->router->set_directory('cli/');
        $this->router->set_class($input['request']['c'] ?? '');
        $this->router->set_method($input['request']['m'] ?? '');

        $this->uri->segments = [
            1 => 'api',
            2 => $input['request']['c'],
            3 => $input['request']['m'],
        ];
        $this->uri->rsegments = [
            1 => $input['request']['c'],
            2 => $input['request']['m'],
        ];

        $_REQUEST = $_GET = $_POST = $input['request']['p'] ?? [];

    }

    final protected function output($data)
    {
        return parent::output($data)->getBody();
    }

}

