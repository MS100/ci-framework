<?php

namespace CI\libraries\DB;

class DB
{
    protected static $dbs = [];

    protected static function getConfig(string $connection)
    {
        static $configs;
        if (!isset($configs)) {
            $configs = config_file('database');
        }

        if (!isset($configs[$connection])) {
            show_error(
                'You have specified an invalid database connection group ('
                .$connection.') in your config/database.php file.'
            );
        }

        return $configs[$connection];
    }

    public static function load(string $connection)
    {
        if (!isset(self::$dbs[$connection])) {
            self::$dbs[$connection] = Factory::load(self::getConfig($connection));
        }

        return self::$dbs[$connection];
    }

    /*public static function keep_alive($name = null)
    {
        if (isset($name)) {
            if (isset(self::$dbs[$name])) {
                if (!self::$dbs[$name]->ping()) {
                    self::$dbs[$name] = null;
                }
            }
        } else {
            if (!empty(self::$dbs)) {
                foreach (self::$dbs as $key => $cache) {
                    if (!$cache->ping()) {
                        self::$dbs[$key] = null;
                    }
                }
            }
        }
    }*/

    public static function reset()
    {
        self::$dbs = [];
    }
}