<?php

namespace CI\core;

use Dotenv\Dotenv;
use Dotenv\Environment\Adapter\PutenvAdapter;
use Dotenv\Environment\Adapter\ServerConstAdapter;
use Dotenv\Environment\DotenvFactory;
use Illuminate\Support\Env;

class CodeIgniter
{
    public static function run()
    {
        defined('APP_PATH') OR exit('No direct script access allowed');
        defined('APP_NAME') OR exit('No direct script access allowed');
        defined('APP_SOURCE') OR exit('No direct script access allowed');
        define('APPPATH', APP_PATH);//兼容ci框架

        define('CI_PATH', dirname(__DIR__).DIRECTORY_SEPARATOR);

        define(
            'BASEPATH',
            APP_PATH.'vendor'.DIRECTORY_SEPARATOR.'codeigniter'
            .DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'system'
            .DIRECTORY_SEPARATOR
        );
        define('VIEWPATH', APP_PATH.'views');

        Dotenv::create(APP_PATH, null, new DotenvFactory([new ServerConstAdapter(), new PutenvAdapter()]))->safeLoad();

        switch (env('APP_ENV')) {
            case 'development':
                error_reporting(-1);
                ini_set('display_errors', 1);
                break;
            default:
                error_reporting(
                    E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT
                    & ~E_USER_NOTICE & ~E_USER_DEPRECATED
                );
                ini_set('display_errors', 0);
                break;
        }

        /*
         * ------------------------------------------------------
         *  Load the framework constants
         * ------------------------------------------------------
         */

        if (file_exists(APP_PATH.'config/constants.php')) {
            require(APP_PATH.'config/constants.php');
        }

        ini_set('date.timezone', env('TIMEZONE'));
        setlocale(LC_ALL, 'en_US.UTF-8');

        /*
         * ------------------------------------------------------
         *  Load the global functions
         * ------------------------------------------------------
         */
        require(BASEPATH.'core/Common.php');


        /*
         * ------------------------------------------------------
         *  Define a custom error handler so we can log PHP errors
         * ------------------------------------------------------
         */
        set_error_handler('_error_handler');
        set_exception_handler('_exception_handler');
        register_shutdown_function('_shutdown_handler');

        /*
         * ------------------------------------------------------
         * Important charset-related stuff
         * ------------------------------------------------------
         *
         * Configure mbstring and/or iconv if they are enabled
         * and set MB_ENABLED and ICONV_ENABLED constants, so
         * that we don't repeatedly do extension_loaded() or
         * function_exists() calls.
         *
         * Note: UTF-8 class depends on this. It used to be done
         * in it's constructor, but it's _not_ class-specific.
         *
         */
        define('MB_ENABLED', true);
        mb_substitute_character('none');
        define('ICONV_ENABLED', true);
    }
}
