<?php

namespace CI\core\Runner\Cli;

class CodeIgniter extends \CI\core\CodeIgniter
{
    public static function run()
    {
        if (PHP_SAPI !== 'cli' && !defined('STDIN')) {
            echo 'If you want cli, you must use cli mode' . PHP_EOL;
            exit(3);
        }

        define('APP_SOURCE', 'cli');

        parent::run();

        chdir(APP_PATH);

        define('RUN_MODE', RM_CLI);

        $CI = new CI();

        $CI->request();
    }
}




