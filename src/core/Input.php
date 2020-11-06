<?php

namespace CI\core;

class Input extends \CI_Input
{
    protected $request_info = [];

    /**
     * Class constructor
     * Determines whether to globally enable the XSS processing
     * and whether to allow the $_GET array.
     *
     * @return    void
     */
    public function __construct()
    {
        $this->_allow_get_array = (config_item('allow_get_array') !== false);
        $this->_enable_xss = (config_item('global_xss_filtering') === true);
        $this->_enable_csrf = (config_item('csrf_protection') === true);
        $this->_standardize_newlines = (bool)config_item('standardize_newlines');

        $this->security =& load_class('Security', 'core');

        // Do we need the UTF-8 class?
        if (UTF8_ENABLED === true) {
            $this->uni =& load_class('Utf8', 'core');
        }

        // Sanitize global arrays
        $this->_sanitize_globals();

        // CSRF Protection check
        if ($this->_enable_csrf === true && is_run_mode(RM_FPM_WEB | RM_SWOOLE_WEB)) {
            $this->security->csrf_verify();
        }

        $this->init_request_info();
        log_message('debug', 'Input Class Initialized');
    }

    /**
     * @var Security
     */
    protected $security;

    public function raw_input_stream($raw = null)
    {
        if (isset($raw)) {
            $this->_raw_input_stream = $raw;
        } else {
            isset($this->_raw_input_stream) || $this->_raw_input_stream = file_get_contents('php://input');
            return $this->_raw_input_stream;
        }
    }

    protected function _clean_input_keys($str, $fatal = true)
    {
        if (!preg_match('/^[a-z0-9:_\/|-]+$/i', $str)) {
            if ($fatal === true) {
                return false;
            } else {
                show_error('存在不允许的字符', 403);
            }
        }

        // Clean UTF-8 if supported
        if (UTF8_ENABLED === true) {
            return $this->uni->clean_string($str);
        }

        return $str;
    }

    public function ip_address()
    {
        if ($this->ip_address === false) {
            $this->ip_address = user_ip();
        }

        return $this->ip_address;
    }
}
