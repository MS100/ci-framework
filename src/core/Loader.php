<?php

namespace CI\core;


class Loader extends \CI_Loader
{

    /**
     * List of paths to load libraries from
     *
     * @var    array
     */
    protected $_ci_library_paths = [CI_PATH, BASEPATH];

    /**
     * List of paths to load models from
     *
     * @var    array
     */
    protected $_ci_model_paths = [];

    /**
     * List of paths to load helpers from
     *
     * @var array
     */
    protected $_ci_helper_paths = [APP_PATH, CI_PATH, BASEPATH];

    /**
     * List of loaded models
     *
     * @var    array
     */
    protected $_ci_models = [];

    // --------------------------------------------------------------------

    public function __construct()
    {
        $this->_ci_ob_level = ob_get_level();
        //$this->_ci_classes =& is_loaded();

        log_message('debug', 'Loader Class Initialized');

        $this->_ci_autoloader();
    }

    // --------------------------------------------------------------------

    /**
     * Model Loader
     * Loads and instantiates models.
     *
     * @param string $model Model name
     * @param string $name An optional object name to assign to
     * @param bool $db_conn An optional database connection configuration to initialize
     *
     * @return    object
     */
    public function model($model, $name = '', $db_conn = false)
    {
        show_error('此功能已废弃');
    }

    // --------------------------------------------------------------------

    /**
     * Database Loader
     *
     * @param mixed $params Database configuration options
     * @param bool $return Whether to return the database object
     * @param bool $query_builder Whether to enable Query Builder
     *                                (overrides the configuration setting)
     *
     * @return    object|bool    Database object if $return is set to TRUE,
     *                    FALSE on failure, CI_Loader instance in any other case
     */
    public function database($params = '', $return = false, $query_builder = null)
    {
        if ($return) {
            return db($params);
        } else {
            db($params);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Driver Loader
     * Loads a driver library.
     *
     * @param string|string[] $library Driver name(s)
     * @param array $params Optional parameters to pass to the driver
     * @param string $object_name An optional object name to assign to
     *
     * @return    object|bool    Object or FALSE on failure if $library is a string
     *                and $object_name is set. CI_Loader instance otherwise.
     */
    public function driver($library, $params = null, $object_name = null)
    {
        if (is_array($library)) {
            foreach ($library as $key => $value) {
                if (is_int($key)) {
                    $this->driver($value, $params);
                } else {
                    $this->driver($key, $params, $value);
                }
            }

            return $this;
        } elseif (empty($library)) {
            return false;
        }

        if (strtolower($library) == 'cache') {
            show_error('Please use cache() function');
        }
        if (!class_exists('CI_Driver_Library', false)) {
            // We aren't instantiating an object here, just making the base class available
            require BASEPATH . 'libraries/Driver.php';
        }

        // We can save the loader some time since Drivers will *always* be in a subfolder,
        // and typically identically named to the library
        if (!strpos($library, '/')) {
            $library = ucfirst($library) . '/' . $library;
        }

        return $this->library($library, $params, $object_name);
    }

    // --------------------------------------------------------------------

    /**
     * Helper Loader
     *
     * @param string|string[] $helpers Helper name(s)
     *
     * @return    object
     */
    public function helper($helpers = [])
    {
        is_array($helpers) OR $helpers = [$helpers];
        foreach ($helpers as &$helper) {
            //$filename = basename($helper);
            //$filepath = ($filename === $helper) ? '' : substr($helper, 0, strlen($helper) - strlen($filename));
            //$filename .= '_helper';
            //$helper = $filepath . $filename;
            $helper .= '_helper';

            if (isset($this->_ci_helpers[$helper])) {
                continue;
            }

            //$ext_helper = $filepath . config_item('subclass_prefix') . $filename;
            foreach ($this->_ci_helper_paths as $path) {
                if (file_exists($path . 'helpers/' . $helper . '.php')) {
                    include_once($path . 'helpers/' . $helper . '.php');

                    $this->_ci_helpers[$helper] = true;
                    log_message('debug', 'Helper loaded: ' . $helper);
                    //continue 2;
                }

                /*if (file_exists($path . 'helpers/' . $ext_helper . '.php')) {
                    include_once($path . 'helpers/' . $ext_helper . '.php');
                }*/
            }

            // unable to load the helper
            if (!isset($this->_ci_helpers[$helper])) {
                show_error('Unable to load the requested file: helpers/' . $helper . '.php');
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Add Package Path
     * Prepends a parent path to the library, model, helper and config
     * path arrays.
     *
     * @param string $path Path to add
     * @param bool $view_cascade (default: TRUE)
     *
     * @return    object
     * @see    CI_Config::$_config_paths
     * @see    CI_Loader::$_ci_library_paths
     * @see    CI_Loader::$_ci_model_paths
     * @see    CI_Loader::$_ci_helper_paths
     */
    public function add_package_path($path, $view_cascade = true)
    {
        show_error('此功能已废弃');
    }

    // --------------------------------------------------------------------

    /**
     * Remove Package Path
     * Remove a path from the library, model, helper and/or config
     * path arrays if it exists. If no path is provided, the most recently
     * added path will be removed removed.
     *
     * @param string $path Path to remove
     *
     * @return    object
     */
    public function remove_package_path($path = '')
    {
        show_error('此功能已废弃');
    }

    // --------------------------------------------------------------------

    /**
     * Internal CI Library Loader
     *
     * @used-by    CI_Loader::library()
     *
     * @param string $class Class name to load
     * @param mixed $params Optional parameters to pass to the class constructor
     * @param string $object_name Optional object name to assign to
     *
     * @return    void
     * @uses       CI_Loader::_ci_init_library()
     */
    protected function _ci_load_library($class, $params = null, $object_name = null)
    {
        // Get the class name, and while we're at it trim any slashes.
        // The directory path can be included as part of the class name,
        // but we don't want a leading slash
        $class = str_replace('.php', '', trim($class, '/'));

        // Was the path included with the class name?
        // We look for a slash to determine this
        if (($last_slash = strrpos($class, '/')) !== false) {
            // Extract the path
            $subdir = substr($class, 0, $last_slash) . '\\';

            // Get the filename from the path
            $class = substr($class, ++$last_slash);
        } else {
            $subdir = '';
        }

        $class = ucfirst($class);

        // Set the variable name we will assign the class to
        // Was a custom class name supplied? If so we'll use it
        if (empty($object_name)) {
            $object_name = strtolower($class);
            if (isset($this->_ci_varmap[$object_name])) {
                $object_name = $this->_ci_varmap[$object_name];
            }
        }

        $CI = ci();

        $class_name = '\\CI\\libraries\\' . $subdir . $class;

        if (class_exists($class_name, true)) {
            if (!isset($CI->$object_name)) {
                return $this->_ci_init_library($class_name, $class, $params, $object_name);
            } elseif (!is_a($CI->$object_name, $class_name)) {
                show_error('Resource ' . $object_name . ' already exists and is not a ' . $class_name . ' instance.');
            }
            log_message('debug', $class . ' class already loaded. Second attempt ignored.');
            return;
        }

        $class_name = 'CI_' . $class;
        // Is this a stock library? There are a few special conditions if so ...
        if (class_exists($class_name, true)) {
            if (!isset($CI->$object_name)) {
                return $this->_ci_init_library($class_name, $class, $params, $object_name);
            } elseif (!is_a($CI->$object_name, $class_name)) {
                show_error('Resource ' . $object_name . ' already exists and is not a ' . $class_name . ' instance.');
            }
            log_message('debug', $class . ' class already loaded. Second attempt ignored.');
            return;
        }

        // If we got this far we were unable to find the requested class.
        show_error('Unable to load the requested class: ' . $class);
    }

    // --------------------------------------------------------------------

    /**
     * Internal CI Library Instantiator
     *
     * @used-by    CI_Loader::_ci_load_library()
     *
     * @param string $class_name Class name
     * @param string $class Class
     * @param array|null|bool $config Optional configuration to pass to the class constructor:
     *                                        FALSE to skip;
     *                                        NULL to search in config paths;
     *                                        array containing configuration data
     * @param string $object_name Optional object name to assign to
     *
     * @return    void
     */
    protected function _ci_init_library($class_name, $class, $config = false, $object_name = null)
    {
        // Is there an associated config file for this class? Note: these should always be lowercase
        if (is_null($config)) {
            $config = config_file(strtolower($class));
        }

        // Save the class name and object name
        $this->_ci_classes[$object_name] = $class;

        // Instantiate the class
        ci()->$object_name = empty($config)
            ? new $class_name()
            : new $class_name($config);
    }

    // --------------------------------------------------------------------

    /**
     * Internal CI Stock Library Loader
     *
     * @used-by    CI_Loader::_ci_load_library()
     *
     * @param string $library_name Library name to load
     * @param string $file_path Path to the library filename, relative to libraries/
     * @param mixed $params Optional parameters to pass to the class constructor
     * @param string $object_name Optional object name to assign to
     *
     * @return    void
     * @uses       CI_Loader::_ci_init_library()
     */
    protected function _ci_load_stock_library($library_name, $file_path, $params, $object_name)
    {
        show_error('此功能已废弃');
    }

    // --------------------------------------------------------------------

    /**
     * CI Autoloader
     * Loads component listed in the config/autoload.php file.
     *
     * @used-by    CI_Loader::initialize()
     * @return    void
     */
    protected function _ci_autoloader()
    {
        $autoload = config_file('autoload');

        // Load any custom config file
        if (isset($autoload['config']) && count($autoload['config']) > 0) {
            foreach ($autoload['config'] as $val) {
                $this->config($val);
            }
        }

        // Autoload languages
        if (isset($autoload['language']) && count($autoload['language']) > 0) {
            $this->language($autoload['language']);
        }
    }
}
