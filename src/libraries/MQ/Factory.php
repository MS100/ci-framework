<?php

namespace CI\libraries\MQ;

class Factory
{

    /**
     * Valid cache drivers
     *
     * @var array
     */
    protected static $valid_drivers = [
        'redis',
        'rabbitmq',
    ];

    public static function load(string $type, array $config)
    {
        if (!isset($config['driver'])) {
            show_error('MQ driver is not set');
        }

        if (!in_array($config['driver'], static::$valid_drivers)) {
            $msg = 'Invalid MQ driver requested: ' . $config['driver'];
            show_error($msg);
        }


        // Use standard class name
        $class_name = __NAMESPACE__ . '\\' . ucfirst($config['driver']) . '\\' . ucfirst($type);

        if (!class_exists($class_name)) {
            $msg = 'Unable to load the requested MQ driver: ' . $class_name;
            show_error($msg);
        }

        // Instantiate, decorate and add child
        $adapter = new $class_name($config);

        return $adapter;
    }

}
