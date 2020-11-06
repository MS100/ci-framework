<?php

namespace CI\core;

class Security extends \CI_Security
{

    public function __construct()
    {
        /**
         * 只有 web 模式下才能使用 csrf 验证
         */
        // Is CSRF protection enabled?
        if (config_item('csrf_protection') && is_run_mode(RM_FPM_WEB | RM_SWOOLE_WEB)) {
            // CSRF config
            foreach (['csrf_expire', 'csrf_token_name', 'csrf_cookie_name'] as $key) {
                if (null !== ($val = config_item($key))) {
                    $this->{'_' . $key} = $val;
                }
            }

            // Append application specific cookie prefix
            if ($cookie_prefix = config_item('cookie_prefix')) {
                $this->_csrf_cookie_name = $cookie_prefix . $this->_csrf_cookie_name;
            }

            // Set the CSRF hash
            $this->_csrf_set_hash();
        }

        $this->charset = strtoupper(config_item('charset'));

        log_message('debug', 'Security Class Initialized');
    }

    /**
     * Show CSRF Error
     *
     * @return    void
     */
    public function csrf_show_error()
    {
        show_error('您的请求没有通过验证', 403);
    }

}
