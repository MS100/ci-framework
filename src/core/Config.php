<?php

namespace CI\core;

class Config extends \CI_Config
{
    public function __construct()
    {
        $this->config = config_file('config', [APP_PATH, CI_PATH]);

        // Set the base_url automatically if none was provided
        if (empty($this->config['base_url'])) {
            $this->set_item('base_url', 'http://localhost/');
        }

        log_message('debug', 'Config Class Initialized');
    }


    // --------------------------------------------------------------------

    /**
     * Load Config File
     *
     * @param string $file Configuration file name
     * @param bool $use_sections Whether configuration values should be loaded into their own section
     * @param bool $fail_gracefully Whether to just return FALSE or display an error message
     *
     * @return    bool    TRUE if the file was loaded correctly or FALSE on failure
     */
    public function load($file = '', $use_sections = false, $fail_gracefully = false)
    {
        $file = ($file === '') ? 'config' : str_replace('.php', '', $file);

        if (!isset($this->is_loaded[$file])) {
            $config = config_file($file);

            if (empty($config)) {
                $this->is_loaded[$file] = false;
            } else {
                $this->is_loaded[$file] = true;

                if ($use_sections === true) {
                    $this->config[$file] = isset($this->config[$file])
                        ? array_merge($this->config[$file], $config)
                        : $config;
                } else {
                    $this->config = array_merge($this->config, $config);
                }
            }
        }

        if ($this->is_loaded[$file]) {
            return true;
        }

        if ($fail_gracefully === true) {
            return false;
        }

        show_error(
            'The configuration file ' .
            $file .
            '.php does not exist or does not appear to contain a valid configuration array.'
        );
    }

    // --------------------------------------------------------------------

    /*public function get_config_paths()
    {
        return $this->_config_paths;
    }*/

    // --------------------------------------------------------------------

    /**
     * Set a config file item
     *
     * @param string $item Config item key
     * @param string $value Config item value
     *
     * @return    void
     */
    public function set_item($item, $value)
    {
        $keys = explode('.', $item);
        $config = &$this->config;
        $last_key = array_pop($keys);

        foreach ($keys as $key) {
            if (!isset($config[$key])) {
                $this->config[$key] = [];
            }

            $config = &$config[$key];
        }

        $config[$last_key] = $value;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch a config file item
     *
     * @param string $item Config item name
     * @param string $index Index name
     *
     * @return    mixed    The configuration item or NULL if the item doesn't exist
     */
    public function item($item, $index = '')
    {
        if ($index !== '') {
            if (isset($this->config[$index])) {
                $config = $this->config[$index];
            } else {
                return null;
            }
        } else {
            $config = $this->config;
        }

        $keys = explode('.', $item);

        foreach ($keys as $key) {
            if (is_array($config) && isset($config[$key])) {
                $config = $config[$key];
            } else {
                return null;
            }
        }

        return value($config);
    }
}