<?php

namespace CI\core\Runner\Cli;

use CI\core\Controller;
use CI\core\Output\Format\Json;

class CI extends \CI\core\CI
{
    protected const CONFIG_TYPE_NAME = 'cli';
    protected const ALLOW_RESPONSE_FORMAT = [
        Json::class,
    ];

    final public function request()
    {
        try {
            PHP_SAPI === 'cli' || defined('STDIN') || exit("WHAT'S THE FUCK!\n");
            @ini_set('memory_limit', '1024M');
            @ini_set('max_execution_time', '0');

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

            $params = array_slice($this->uri->rsegments, 2);

            $CTL = new $class_name();

            $results = $CTL->$method(...$params);
        } catch (\Throwable $e) {
            $results = dispose_exception($e);
        }

        $this->output($results);
    }

    final protected function output($data)
    {
        echo parent::output($data);
    }
}
