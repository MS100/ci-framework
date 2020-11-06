<?php

namespace CI\libraries\Session;

use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\Store;

class Session
{
    protected static $session_handler;

    protected static $config_tpl
        = [
            'lifetime'        => 600, //分钟
            'expire_on_close' => false,
            'cookie'          => 'session',
            'domain'          => '',
            'path'            => '/',
            'secure'          => false,
            'http_only'       => true,
            'store'           => Store::class,
        ];

    protected static function getConfig()
    {
        static $config;

        if (!isset($config)) {
            $config = array_merge(self::$config_tpl, config_file('session'));
        }

        return $config;
    }

    /**
     * @return Store
     */
    public static function load()
    {
        $config = self::getConfig();
        //$handler = $config['driver'] ?? 'cache';
        if (!isset(self::$session_handler)) {
            self::$session_handler = new CacheBasedSessionHandler(
                cache('session'),
                $config['lifetime']
            );
        }

        $store_class = $config['store'];
        $session = new $store_class(
            $config['cookie'],
            self::$session_handler,
            $_COOKIE[$config['cookie']] ?? null
        );

        $expire = $config['expire_on_close']
            ? 0
            : time() + $config['lifetime'] * 60;

        setcookie(
            $session->getName(),
            $session->getId(),
            $expire,
            $config['path'],
            $config['domain'],
            $config['secure'],
            $config['http_only']
        );

        return $session;
    }

}