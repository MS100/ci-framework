<?php

namespace CI\core;

class Router extends \CI_Router
{
    /**
     * Class constructor
     * Runs the route mapping function.
     *
     * @param array $routing
     *
     * @return    void
     */
    public function __construct($routing = null)
    {
        $this->config =& load_class('Config', 'core');
        $this->uri =& load_class('URI', 'core');

        // If a directory override is configured, it has to be set before any dynamic routing logic
        is_array($routing) && isset($routing['directory']) && $this->set_directory($routing['directory']);
        $this->_set_routing();

        // Set any routing overrides that may exist in the main index file
        if (is_array($routing)) {
            empty($routing['controller']) OR $this->set_class($routing['controller']);
            empty($routing['function']) OR $this->set_method($routing['function']);
        }

        log_message('debug', 'Router Class Initialized');
    }

    /**
     * 将配置文件的读取放入到初始化方法中
     */
    protected function _set_routing()
    {
        // Load the routes.php file. It would be great if we could
        // skip this for enable_query_strings = TRUE, but then
        // default_controller would be empty ...
        $route = config_file('routes/' . ci()->getConfigTypeName());

        // Validate & get reserved routes
        if (isset($route) && is_array($route)) {
            isset($route['default_controller']) && $this->default_controller = $route['default_controller'];
            //isset($route['translate_uri_dashes']) && $this->translate_uri_dashes = $route['translate_uri_dashes'];
            unset($route['default_controller'], $route['translate_uri_dashes']);
            $this->routes = $route;
        }

        // Is there anything to parse?
        if ($this->uri->uri_string !== '') {
            $this->_parse_routes();
        } else {
            $this->_set_default_controller();
        }
    }

    protected function _validate_request($segments)
    {
        $c = count($segments);
        $directory_override = isset($this->directory);

        // Loop through our segments and return as soon as a controller
        // is found or when such a directory doesn't exist
        while ($c-- > 0) {
            $test = $this->directory . to_big_camel_case($segments[0]);

            if (!file_exists(ci()->getControllerPath() . $test . '.php')
                && $directory_override === false
                && is_dir(ci()->getControllerPath() . $test)
            ) {
                $this->set_directory(array_shift($segments), true);
                continue;
            }

            return $segments;
        }

        // This means that all segments were actually directories
        return $segments;
    }

    protected function _set_default_controller()
    {
        $this->directory = null;

        if (empty($this->default_controller)) {
            show_error('没有设置默认路由', 404);
        }

        $ex = explode('/', $this->default_controller);
        if (!$this->set_uri_rsegments($ex)) {
            show_error('默认路由设置错误，请按照 [d/]c/m 格式填写', 404);
        }

        log_message('debug', 'No URI present. Default controller set.');
    }

    protected function _set_request($segments = [])
    {
        if (is_run_mode(RM_FPM_WEB | RM_SWOOLE_WEB | RM_CLI)) {
            parent::_set_request($segments);
        } elseif (!$this->set_uri_rsegments($segments)) {
            show_error('请指定要请求的接口', 404);
        }
    }

    public function override_404()
    {
        if (!empty($this->routes['404_override'])) {
            $ex = explode('/', $this->routes['404_override']);
            if (!$this->set_uri_rsegments($ex)) {
                return false;
            }

            $class_name = $this->fetch_class_name();
            if (class_exists($class_name)) {
                return true;
            }
        }
        return false;
    }

    protected function set_uri_rsegments(array $segments)
    {
        if (count($segments) < 2) {
            return false;
        }
        $method = array_pop($segments);
        $class = array_pop($segments);

        if (count($segments) > 0) {
            $this->set_directory(implode('/', $segments));
        }

        $this->set_class($class);
        $this->set_method($method);

        // Assign routed segments, index starting from 1
        $this->uri->rsegments = [
            1 => $class,
            2 => $method,
        ];
        return true;
    }

    public function fetch_class_name()
    {
        return strtr('App\\' . ci()->getControllerDir() . $this->directory . $this->class, '/', '\\');
    }

    public function fetch_directory_class_method()
    {
        return $this->directory . $this->class . '/' . $this->method;
    }

    // --------------------------------------------------------------------

    /**
     * Set directory name
     *
     * @param string $dir Directory name
     * @param bool $append Whether we're appending rather than setting the full value
     *
     * @return    void
     */
    public function set_directory($dir, $append = false)
    {
        if ($append !== true OR empty($this->directory)) {
            $this->directory = trim(to_big_camel_case($dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        } else {
            $this->directory .= trim(to_big_camel_case($dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Set class name
     *
     * @param string $class Class name
     *
     * @return    void
     */
    public function set_class($class)
    {
        $this->class = to_big_camel_case($class);
    }

    // --------------------------------------------------------------------

    /**
     * Set method name
     *
     * @param string $method Method name
     *
     * @return    void
     */
    public function set_method($method)
    {
        $this->method = to_small_camel_case($method);
    }
}
