<?php

namespace CI\core\Runner\Swoole\Web;

//error_reporting(E_ALL | E_STRICT);


class Manager
{
    const DEFAULT_CONFIG = 'SwooleManager';

    private static $allow_swoole_config_key = [
        'worker_num',
        'max_request',
        'daemonize',
        'log_file',
        'log_level',
        'user',
        'pid_file',
        'package_max_length',
        'open_cpu_affinity',
        'cpu_affinity_ignore',
        'open_tcp_nodelay',
        'socket_buffer_size',
        'buffer_output_size',
    ];
    private static $config = [];

    private static $check_code = false;
    private static $host;
    private static $port;

    private static $server;

    public static function start()
    {
        self::getOpt();
        self::setProperty();

        self::evolve();

        self::$server->start();
    }

    private static function evolve()
    {
        self::$server = new \Swoole\Http\Server(self::$host, self::$port);

        self::$server->set(self::$config);

        self::$server->on(
            'Request',
            function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) {
                ob_start();
                $CI = new CI();
                $data = $CI->request($request);

                foreach ($data->getHeaders() as $k => $v) {
                    $response->header($k, $v);
                }

                while (ob_get_level() > 1) {
                    ob_end_clean();
                }

                $ob_content = ob_get_clean();

                if (strlen($ob_content)) {
                    $response->end($ob_content . $data->getBody());
                } else {
                    $response->end($data->getBody());
                }

                unset($ob_content);
            }
        );
        /*self::$server->on(
            'WorkerStart',
            function (\Swoole\Server $serv, $worker_id)
            {

            }
        );*/
    }

    private static function getOpt()
    {
        $opts = getopt('ac:dD:h:Hl:p:P:s:u:v:r:Z');

        if (isset($opts['H'])) {
            self::showHelp();
        }

        if (isset($opts['c'])) {
            $config_file = $opts['c'];
        }

        if (isset($config_file)) {
            if (file_exists($config_file)) {
                self::parseConfig($config_file);
            } else {
                self::showHelp('Config file ' . $config_file . ' not found.');
            }
        } elseif (file_exists($config_file = APP_PATH . 'config/' . ENVIRONMENT . '/swoole_http.php')
            || file_exists($config_file = APP_PATH . 'config/swoole_http.php')
        ) {
            self::parseConfig($config_file);
        }

        /**
         * command line opts always override config file
         */

        if (isset($opts['D'])) {
            self::$config['worker_num'] = (int)$opts['D'];
        }

        if (isset($opts['r'])) {
            self::$config['max_request'] = (int)$opts['r'];
        }

        if (isset($opts['a'])) {
            self::$config['auto_update'] = 1;
        }

        /**
         * If we want to daemonize, fork here and exit
         */
        if (isset($opts['d'])) {
            self::$config['daemonize'] = 1;
        }

        if (isset($opts['l'])) {
            self::$config['log_file'] = $opts['l'];
        }

        if (isset($opts['v'])) {
            self::$config['log_level'] = (int)$opts['v'];
        }

        if (isset($opts['u'])) {
            self::$config['user'] = $opts['u'];
        }

        if (isset($opts['P'])) {
            self::$config['pid_file'] = $opts['P'];
        }


        if (isset($opts['h'])) {
            self::$config['host'] = $opts['h'];
        }

        if (isset($opts['p'])) {
            self::$config['port'] = (int)$opts['p'];
        }

        /**
         * Debug option to dump the config and exit
         */
        if (isset($opts['Z'])) {
            print_r(self::$config);
            exit();
        }
    }

    /**
     * Parses the config file
     *
     * @param string $file The config file. Just pass so we don't have
     *                              to keep it around in a var
     */
    private static function parseConfig($file)
    {
        echo 'Loading configuration from ', $file, PHP_EOL;

        if (substr($file, -4) == '.php') {
            $swoole_config = require $file;
        }

        if (empty($swoole_config) || !isset($swoole_config[self::DEFAULT_CONFIG])) {
            self::showHelp("No configuration found in $file");
        }

        self::$config = $swoole_config[self::DEFAULT_CONFIG];
    }

    private static function setProperty()
    {
        if (isset(self::$config['auto_update'])) {
            self::$check_code = (bool)self::$config['auto_update'];
        }

        if (isset(self::$config['host'])) {
            self::$host = self::$config['host'];
        } else {
            self::$host = '0.0.0.0';
        }

        if (isset(self::$config['port'])) {
            self::$port = (int)self::$config['port'];
        } else {
            self::$port = 9502;
        }

        self::$config = array_intersect_key(self::$config, array_flip(self::$allow_swoole_config_key));
    }

    /**
     * Shows the scripts help info with optional error message
     */
    private static function showHelp($msg = '')
    {
        if ($msg) {
            echo "ERROR:\n";
            echo '  ' . wordwrap($msg, 72, "\n  ") . "\n\n";
        }
        echo "Swoole http manager script\n\n";
        echo "USAGE:\n";
        echo "  # " . basename(__FILE__) . " -h | -c CONFIG [-l LOG_FILE] [-d] [-v LOG_LEVEL] [-a] [-P PID_FILE]\n\n";
        echo "OPTIONS:\n";
        echo "  -a             检查代码更新自动重启\n";
        echo "  -c CONFIG      加载的配置文件\n";
        echo "  -d             守护模式\n";
        echo "  -D NUMBER      开启的work数量\n";
        echo "  -h HOST        绑定的IP地址\n";
        echo "  -H             显示帮助\n";
        echo "  -l LOG_FILE    日志文件位置\n";
        echo "  -p NUMBER      监听的端口\n";
        echo "  -P PID_FILE    进程PID文件存储位置\n";
        echo "  -u USERNAME    以某位用户的身份执行\n";
        echo "  -v LOG_LEVEL   日志等级，0=>DEBUG 1=>TRACE 2=>INFO 3=>NOTICE 4=>WARNING 5=>ERROR\n";
        echo "  -r NUMBER      每个work最大执行请求数，超过此数量会重启\n";
        echo "  -Z             打印配置文件并退出\n";
        echo "\n";
        exit();
    }

}