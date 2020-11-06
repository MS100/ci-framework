<?php

namespace CI\libraries\DB;

use CI\libraries\DB\Drivers\Mongo;
use CI\libraries\DB\Drivers\Mysqli;

class Factory
{

    /**
     * Allow cache drivers
     *
     * @var array
     */
    protected static $allow_drivers
        = [
            'mysqli' => Mysqli::class,
            'mongo'  => Mongo::class,
        ];

    /**
     * @param array $config
     *
     * @return Mongo|Mysqli
     * @throws \CI\core\Exceptions\SeniorException
     */
    public static function load(array $config = [])
    {
        if (empty($config['dbdriver'])) {
            show_error('You have not selected a database type to connect to.');
        }
        $driver = strtolower($config['dbdriver']);
        // See if requested child is a valid driver
        if (!isset(self::$allow_drivers[$driver])) {
            // The requested driver isn't valid!
            $msg = 'Invalid DB driver requested: '.$driver;
            show_error($msg);
        }

        $class_name = self::$allow_drivers[$driver];

        return new $class_name($config);
    }
}
