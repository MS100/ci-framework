<?php

namespace CI\libraries;


class Rabbitmq
{
    /**
     * @var  \AMQPConnection[]
     */
    protected static $mqs = [];

    protected static $config_tpl = [
        'host' => '127.0.0.1',
        'port' => 5672,
        'login' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
    ];

    private static function getConfig($name)
    {
        static $configs;

        if (!isset($configs)) {
            $configs = config_file('rabbitmq');
        }

        if (!isset($configs[$name])) {
            show_error('You have use an undefined rabbitmq group (' . $name . ') in your config/rabbitmq.php file.');
        }

        return $configs[$name];
    }

    /**
     * @param $name
     *
     * @return \AMQPConnection
     */
    public static function load($name)
    {
        if (!isset(self::$mqs[$name])) {
            $config = self::getConfig($name);
            $config += self::$config_tpl;
            self::$mqs[$name] = new \AMQPConnection($config);
            self::$mqs[$name]->pconnect();
        }elseif(!self::$mqs[$name]->isConnected()) {
            self::$mqs[$name]->preconnect();
        }

        return self::$mqs[$name];
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public static function unload($name)
    {
        if (isset(self::$mqs[$name])) {
            try {
                self::$mqs[$name]->pdisconnect();
            } catch (\Throwable $e) {
            }
        }
        unset(self::$mqs[$name]);

        return true;
    }
}
