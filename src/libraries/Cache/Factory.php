<?php

namespace CI\libraries\Cache;

use CI\libraries\Cache\Drivers\Dummy;
use CI\libraries\Cache\Drivers\File;
use CI\libraries\Cache\Drivers\Memcached;
use CI\libraries\Cache\Drivers\Redis;

class Factory
{

    /**
     * Allow cache drivers
     *
     * @var array
     */
    protected static $allow_drivers
        = [
            'dummy'     => Dummy::class,
            'file'      => File::class,
            'memcached' => Memcached::class,
            'redis'     => Redis::class,
        ];

    /**
     * Fallback driver
     *
     * @var string
     */
    protected static $_backup_driver = 'dummy';

    /**
     * @param array $config
     *
     * @return Driver
     * @throws \CI\core\Exceptions\SeniorException
     */
    public static function load(array $config = [])
    {
        isset($config['driver']) || $config['driver'] = 'dummy';

        $driver = self::loadDriver(
            $config['driver'],
            $config['servers'] ?? [],
            $config['key_prefix'] ?? ''
        );

        // If the specified driver isn't available, check the backup.
        if (!$driver->isSupported()) {
            // Backup is supported. Set it to primary.
            log_message(
                'warn',
                'Cache driver "'.
                $config['driver'].
                '" is unavailable. Falling back to "'.
                self::$_backup_driver.
                '" backup driver.'
            );
            $driver = self::loadDriver(self::$_backup_driver);
        }

        return $driver;
    }

    /**
     * @param string $driver
     * @param array  $config
     * @param string $key_prefix
     *
     * @return Driver
     * @throws \CI\core\Exceptions\SeniorException
     */
    private static function loadDriver(
        string $driver,
        array $config = [],
        string $key_prefix = ''
    ) {
        $driver = strtolower($driver);
        // See if requested child is a valid driver
        if (!isset(self::$allow_drivers[$driver])) {
            // The requested driver isn't valid!
            $msg = 'Invalid Cache driver requested: '.$driver;
            show_error($msg);
        }

        // Use standard class name
        $class_name = self::$allow_drivers[$driver];

        // Instantiate, decorate and add child
        return new $class_name($config, $key_prefix);
    }

}
