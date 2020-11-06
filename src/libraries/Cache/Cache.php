<?php

namespace CI\libraries\Cache;

class Cache
{
    /**
     * @var Driver[]
     */
    protected static $caches = [];

    protected static function getConfig(string $connection)
    {
        static $configs;
        if (!isset($configs)) {
            $configs = config_file('cache');
        }

        if (!isset($configs[$connection])) {
            show_error(
                'You have use an undefined cache group ('.$connection
                .') in your config/cache.php file.'
            );
        }

        return $configs[$connection];
    }

    /**
     * @param string $connection
     *
     * @return Driver
     * @throws \CI\core\Exceptions\SeniorException
     */
    public static function load(string $connection)
    {
        if (!isset(self::$caches[$connection])) {
            self::$caches[$connection] = Factory::load(
                self::getConfig($connection)
            );
        }

        return self::$caches[$connection];
    }

    public static function keepAlive(?string $connection = null)
    {
        if (isset($connection)) {
            if (isset(self::$caches[$connection])) {
                if (!self::$caches[$connection]->ping()) {
                    self::$caches[$connection] = null;
                }
            }
        } elseif (!empty(self::$caches)) {
            foreach (self::$caches as $connection => $cache) {
                if (!$cache->ping()) {
                    self::$caches[$connection] = null;
                }
            }
        }
    }

    /*public static function reset()
    {
        if (!empty(self::$caches)) {
            foreach (self::$caches as $connection => $cache) {
                self::$caches[$connection] = null;
            }
        }

        self::$caches = [];
    }*/
}