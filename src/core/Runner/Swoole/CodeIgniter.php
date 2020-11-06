<?php

namespace CI\core\Runner\Swoole;

use CI\core\Runner\Swoole;


class CodeIgniter extends \CI\core\CodeIgniter
{

    public static function run()
    {
        if (PHP_SAPI !== 'cli' && !defined('STDIN')) {
            echo 'If you want swoole, you must use cli mode' . PHP_EOL;
            exit(3);
        }

        $opts = getopt('s:');
        if (!isset($opts['s'])) {
            exit('-s必填' . PHP_EOL);
        }

        if (!in_array($opts['s'], ['web', 'tcp', 'queue'])) {
            exit('-s 必须在<http|tcp|queue>范围内' . PHP_EOL);
        }

        define('APP_SOURCE', $opts['s']);

        $_SERVER['argv'] = [];

        parent::run();

        switch (APP_SOURCE) {
            case 'queue':
                define('RUN_MODE', RM_SWOOLE_QUEUE);
                Swoole\Queue\Manager::start();
                break;
            case 'tcp':
                define('RUN_MODE', RM_SWOOLE_TCP);
                Swoole\Tcp\Manager::start();
                break;
            default:
                define('RUN_MODE', RM_SWOOLE_WEB);
                Swoole\Web\Manager::start();
                break;
        }
    }
}