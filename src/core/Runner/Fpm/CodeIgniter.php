<?php

namespace CI\core\Runner\Fpm;

use CI\core\Runner\Fpm\Api\CI as apiCI;
use CI\core\Runner\Fpm\Web\CI as webCI;

class CodeIgniter extends \CI\core\CodeIgniter
{
    public static function run()
    {
        define('APP_SOURCE', $_SERVER['APP_SOURCE'] ?? 'web');

        parent::run();

        switch (APP_SOURCE) {
            case 'api':
                define('RUN_MODE', RM_FPM_API);
                $CI = new apiCI();
                break;
            default:
                define('RUN_MODE', RM_FPM_WEB);
                $CI = new webCI();
        }

        $CI->request();
    }
}
