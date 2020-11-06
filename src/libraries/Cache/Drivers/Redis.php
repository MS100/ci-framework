<?php

namespace CI\libraries\Cache\Drivers;

use CI\libraries\Cache\Driver;

class Redis extends Driver
{
    /**
     * Redis connection
     *
     * @var    \Redis
     */
    protected $_redis;


    // ------------------------------------------------------------------------

    /**
     * Class constructor
     * Setup Redis
     * Loads Redis config file if present. Will halt execution
     * if a Redis connection can't be established.
     *
     * @see        Redis::connect()
     */
    public function __construct(array $config, $key_prefix = '')
    {
        if (!$this->isSupported()) {
            log_message(
                'critical',
                'Cache: Failed to create Redis object; extension not loaded?'
            );

            return;
        }

        try {
            $this->_redis = redis($config['connection'] ?? 'default');
        } catch (\RedisException $e) {
            log_message(
                'critical',
                'Cache: Redis connection refused ('.$e->getMessage().')'
            );
        }

        parent::__construct(
            $config,
            $this->_redis->getOption(\Redis::OPT_PREFIX)
        );
    }

    // ------------------------------------------------------------------------

    public function has($key)
    {
        return (bool)$this->_redis->exists($key);
    }

    // ------------------------------------------------------------------------

    public function get($key, $default = null)
    {
        $res = $this->_redis->get($key);

        if ($res === false && $this->_redis->exists($key) == 0) {
            return $default;
        }

        return $res;
    }

    // ------------------------------------------------------------------------

    public function getMultiple($keys, $default = null)
    {
        $data = $this->_redis->mget($keys);

        $res = [];
        foreach ($keys as $index => $key) {
            if ($data[$index] === false && $this->_redis->exists($key) == 0) {
                $res[$key] = $default;
            } else {
                $res[$key] = $data[$index];
            }
        }

        return $res;
    }

    // ------------------------------------------------------------------------

    public function add($key, $data, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl) {
            return $this->_redis->set($key, $data, ['nx', 'ex' => $ttl]);
        } else {
            return $this->_redis->setnx($key, $data);
        }
    }

    // ------------------------------------------------------------------------

    public function set($key, $data, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->delete($key);
        } elseif ($ttl > 0) {
            return (bool)$this->_redis->setex($key, $ttl, $data);
        } else {
            return (bool)$this->_redis->set($key, $data);
        }
    }

    // ------------------------------------------------------------------------

    public function replace($key, $data, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->delete($key);
        }
        $param = ['xx'];
        if ($ttl) {
            $param['ex'] = $ttl;
        }

        return $this->_redis->set($key, $data, $param);
    }

    // ------------------------------------------------------------------------

    public function setMultiple($values, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->deleteMultiple(array_keys($values));
        } elseif ($ttl) {
            $this->_redis->multi();
            foreach ($values as $k => $v) {
                $this->_redis->setex($k, $ttl, $v);
            }

            return $this->_redis->exec() !== false;
        } else {
            return (bool)$this->_redis->mset($values);
        }
    }

    // ------------------------------------------------------------------------

    public function delete($key)
    {
        return (bool)$this->_redis->del($key);
    }

    // ------------------------------------------------------------------------

    public function deleteMultiple($keys)
    {
        return (bool)$this->_redis->del($keys);
    }

    // ------------------------------------------------------------------------


    public function increment($key, $value = 1)
    {
        return $this->_redis->incrBy($key, $value);
    }

    // ------------------------------------------------------------------------

    public function decrement($key, $value = 1)
    {
        return $this->_redis->decrBy($key, $value);
    }

    // ------------------------------------------------------------------------

    /**
     * Clean cache
     *
     * @return    bool
     * @see        Redis::flushDB()
     */
    /*public function clean()
    {
        return $this->_redis->flushDB();
    }*/

    // ------------------------------------------------------------------------

    /**
     * Get cache driver info
     *
     * @param string $type    Not supported in Redis.
     *                        Only included in order to offer a
     *                        consistent cache API.
     *
     * @return    mixed
     * @see        Redis::info()
     */
    public function cacheInfo($type = null)
    {
        return $this->_redis->info();
    }


    // ------------------------------------------------------------------------

    /**
     * Check if Redis driver is supported
     *
     * @return    bool
     */
    public function isSupported()
    {
        return extension_loaded('redis');
    }

    public function ping()
    {
        try {
            $this->_redis->ping();

            return true;
        } catch (\Exception $e) {
            log_message('warn', 'Cache: Redis has gone away, reconnect!');

            //$this->connect();
            return false;
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Class destructor
     * Closes the connection to Redis if present.
     *
     * @return    void
     */
    public function __destruct()
    {
        if ($this->_redis) {
            $this->_redis->close();
            $this->_redis = null;
        }
    }
}
