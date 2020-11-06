<?php

namespace CI\core\Runner\Swoole\Queue;


use CI\core\Controller;
use CI\core\Exceptions\FormException;
use CI\core\Output\Format\Json;

class CI extends \CI\core\CI
{
    protected const CONFIG_TYPE_NAME = 'queue';
    protected const ALLOW_RESPONSE_FORMAT = [
        Json::class,
    ];

    final public function request($callback, $msg)
    {
        try {
            $this->input($callback);
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

            $results = $CTL->$method($msg);
        } catch (FormException $e) {
            $results = true;
        } catch (\Throwable $e) {
            $results = dispose_exception($e);
        }


        return $this->output($results);
    }

    final private function input($callback)
    {
        $this->flushResources();

        $_SERVER = [];

        /*$_SERVER['SERVER_ADDR'] = $input['header']['local_ip'] ?? null;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $input['header']['user_ip'] ?? null;
        $_SERVER['REMOTE_ADDR'] = $input['header']['client_ip'] ?? null;
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        */

        $_SERVER['PHP_SELF'] = 'swoole.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['argv'] = ['swoole.php'];
        array_push($_SERVER['argv'], ...explode('/', $callback));

        $_REQUEST = $_GET = $_POST = [];
    }

    final protected function output($data)
    {
        parent::output($data);

        return $this->output->isSuccess();
    }

}

