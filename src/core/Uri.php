<?php

namespace CI\core;

class Uri extends \CI_URI
{
    /**
     * Class constructor
     *
     * @return    void
     */
    public function __construct()
    {
        $this->config =& load_class('Config', 'core');

        $this->_permitted_uri_chars = $this->config->item('permitted_uri_chars');

        if (is_run_mode(RM_FPM_WEB | RM_SWOOLE_WEB)) {
            $protocol = $this->config->item('uri_protocol');
            empty($protocol) && $protocol = 'REQUEST_URI';

            switch ($protocol) {
                case 'AUTO': // For BC purposes only
                case 'REQUEST_URI':
                    $uri = $this->_parse_request_uri();
                    break;
                case 'QUERY_STRING':
                    $uri = $this->_parse_query_string();
                    break;
                case 'PATH_INFO':
                default:
                    $uri = isset($_SERVER[$protocol])
                        ? $_SERVER[$protocol]
                        : $this->_parse_request_uri();
                    break;
            }
        } else {
            $uri = $this->_parse_argv();
        }

        $this->_set_uri_string($uri);

        log_message('debug', 'URI Class Initialized');
    }

    /**
     * Set URI String
     *
     * @param string $str
     *
     * @return    void
     */
    protected function _set_uri_string($str)
    {
        if (is_run_mode(RM_FPM_WEB | RM_SWOOLE_WEB)) {
            parent::_set_uri_string($str);
        } else {
            // Filter out control characters and trim slashes
            $this->uri_string = trim(remove_invisible_characters($str, false), '/');

            if ($this->uri_string !== '') {
                $this->segments[0] = null;
                // Populate the segments array
                foreach (explode('/', trim($this->uri_string, '/')) as $val) {
                    $val = trim($val);
                    // Filter segments for security
                    $this->filter_uri($val);

                    if ($val !== '') {
                        $this->segments[] = $val;
                    }
                }

                unset($this->segments[0]);
            }
        }
    }
}