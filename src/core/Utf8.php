<?php

namespace CI\core;

class Utf8 extends \CI_Utf8
{
    public function __construct()
    {
        if (!defined('UTF8_ENABLED')) {
            if (
                defined('PREG_BAD_UTF8_ERROR')                // PCRE must support UTF-8
                && (ICONV_ENABLED === true OR MB_ENABLED === true)    // iconv or mbstring must be installed
                && strtoupper(config_item('charset')) === 'UTF-8'    // Application charset must be UTF-8
            ) {
                define('UTF8_ENABLED', true);
                log_message('debug', 'UTF-8 Support Enabled');
            } else {
                define('UTF8_ENABLED', false);
                log_message('debug', 'UTF-8 Support Disabled');
            }
        }

        log_message('debug', 'Utf8 Class Initialized');
    }
}
