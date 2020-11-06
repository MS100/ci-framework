<?php

namespace CI\core\Runner\Swoole\Web;

use CI\core\Controller;
use CI\core\Output\Format\Download;
use CI\core\Output\Format\Image;
use CI\core\Output\Format\Json;
use CI\core\Output\Format\Jsonp;
use CI\core\Output\Format\Redirect;
use CI\core\Output\Format\Text;
use CI\core\Output\Format\Twig;
use CI\core\Output\Response;

class CI extends \CI\core\CI
{
    protected const CONFIG_TYPE_NAME = 'web';
    protected const ALLOW_RESPONSE_FORMAT = [
        Json::class,
        Twig::class,
        Jsonp::class,
        Download::class,
        Image::class,
        Redirect::class,
        Text::class,
    ];

    /**
     * run
     *
     * @param \Swoole\Http\Request $request
     *
     * @access public
     * @return Response
     */
    final public function request(\Swoole\Http\Request $request)
    {
        try {
            $this->input($request);
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
                if ($this->router->override_404()) {
                    $class_name = $this->router->fetch_class_name();
                    $method = $this->router->method;
                } else {
                    show_404($this->uri->uri_string);
                }
            }

            $params = array_slice($this->uri->rsegments, 2);

            $this->hooks->call_hook('pre_controller');

            /*if (!isset($this->objects[$class_name])) {
                $this->objects[$class_name] = new $class_name();
            }*/
            $CTL = new $class_name();

            $this->hooks->call_hook('post_controller_constructor');

            //$results = $this->objects[$class_name]->$method(...$params);
            $results = $CTL->$method(...$params);

            $this->hooks->call_hook('post_controller');
        } catch (\Throwable $e) {
            $results = dispose_exception($e);
        }

        return $this->output($results);
    }

    final private function input(\Swoole\Http\Request $request)
    {
        $this->flushResources();

        $this->setServers($request);


        //var_dump($this->router->directory,$this->router->class,$this->router->method);


        $_GET = $request->get ?? [];
        $_POST = $request->post ?? [];
        $_REQUEST = array_merge($_GET, $_POST);
        $_COOKIE = $request->cookie ?? [];
        $_FILES = $request->files ?? [];


        if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' && !empty($input_stream = $request->rawContent())) {
            $input_stream = json_decode($input_stream, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $_POST = $input_stream;
            }
        }

        $this->input->raw_input_stream($request->rawContent());
    }

    final private function setServers(\Swoole\Http\Request $request)
    {
        $_SERVER = [];

        if (isset($request->header)) {
            foreach ($request->header as $k => $v) {
                $_SERVER['HTTP_' . strtoupper(strtr($k, ['-' => '_']))] = $v;
            }
        }

        if (isset($request->server)) {
            foreach ($request->server as $k => $v) {
                $_SERVER[strtoupper($k)] = $v;
            }
        }
        /*$_SERVER['SERVER_ADDR'] = $input['header']['local_ip'] ?? null;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $input['header']['user_ip'] ?? null;
        $_SERVER['REMOTE_ADDR'] = $input['header']['client_ip'] ?? null;

        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        $_SERVER['REQUEST_METHOD'] = 'POST';*/
    }

    final protected function output($data)
    {
        return parent::output($data);
    }
}

