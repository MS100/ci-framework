<?php

namespace CI\libraries;


class Redis
{
    /**
     * @var \Redis[]
     */
    protected static $redis_arr = [];

    /**
     * Default config
     *
     * @static
     * @var    array
     */
    protected const DEFAULT_CONFIG
        = [
            'socket_type'     => 'tcp',
            'host'            => '127.0.0.1',
            'password'        => null,
            'port'            => 6379,
            'connect_timeout' => 0,
            'read_timeout'    => 0,
            'serializer'      => \Redis::SERIALIZER_PHP,
        ];

    protected static function getConfig($name)
    {
        static $configs;

        if (!isset($configs)) {
            $configs = config_file('redis');
        }

        if (!isset($configs[$name])) {
            show_error(
                'You have use an undefined redis group ('.$name
                .') in your config/redis.php file.'
            );
        }

        return $configs[$name];
    }

    public static function load(string $redis_config_key)
    {
        if (!isset(self::$redis_arr[$redis_config_key])) {
            $config = self::getConfig($redis_config_key);
            self::$redis_arr[$redis_config_key] = self::connect(
                $config['servers'] ?? [],
                $config['key_prefix'] ?? ''
            );
        }

        return self::$redis_arr[$redis_config_key];
    }

    /**
     * @param array  $config
     * @param string $key_prefix
     *
     * @return \Redis
     */
    public static function connect(array $config, $key_prefix = '')
    {
        if (!extension_loaded('redis')) {
            show_error('Failed to create Redis object; extension not loaded?');
        }

        if (!empty($config)) {
            $config = array_merge(self::DEFAULT_CONFIG, $config);
        }

        $redis = new \Redis();

        if ($config['socket_type'] === 'unix') {
            $success = $redis->pconnect($config['socket']);
        } else // tcp socket
        {
            $success = $redis->pconnect(
                $config['host'],
                $config['port'],
                $config['connect_timeout']
            );
        }

        if (!$success) {
            show_error('Redis connection failed. Check your configuration.');
        }

        if ((string)$config['password'] !== ''
            && !$redis->auth(
                $config['password']
            )
        ) {
            show_error('Redis authentication failed.');
        }

        (string)$key_prefix !== ''
        && $redis->setOption(
            \Redis::OPT_PREFIX,
            (string)$key_prefix
        );
        (int)$config['read_timeout']
        && $redis->setOption(
            \Redis::OPT_READ_TIMEOUT,
            (int)$config['read_timeout']
        );

        $config['serializer']
        && $redis->setOption(
            \Redis::OPT_SERIALIZER,
            $config['serializer']
        );

        if (!empty($config['database'])) {
            $redis->select(
                (int)$config['database']
            );
        }

        return $redis;
    }

    public static function keepAlive($name = null)
    {
        if (isset($name)) {
            if (isset(self::$redis_arr[$name])) {
                if (!self::$redis_arr[$name]->ping()) {
                    self::$redis_arr[$name] = null;
                }
            }
        } elseif (!empty(self::$redis_arr)) {
            foreach (self::$redis_arr as $key => $redis) {
                if (!$redis->ping()) {
                    self::$redis_arr[$key] = null;
                }
            }
        }
    }
}
