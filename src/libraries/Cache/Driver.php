<?php

namespace CI\libraries\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;


abstract class Driver implements Repository, Store
{
    /**
     * Cache key prefix
     *
     * @var    string
     */
    public $key_prefix = '';

    public function __construct(array $config, $key_prefix)
    {
        $this->key_prefix = $key_prefix;
    }

    // ------------------------------------------------------------------------

    public function save($key, $data, $ttl = null)
    {
        return $this->set($key, $data, $ttl);
    }

    // ------------------------------------------------------------------------

    abstract public function replace($key, $data, $ttl = null);


    // ------------------------------------------------------------------------

    final public function clear()
    {
        return false;
    }

    // ------------------------------------------------------------------------

    /**
     * Clean cache
     *
     * @return    bool
     * @see        Redis::flushDB()
     */
    final public function clean()
    {
        return $this->clear();
    }

    // ------------------------------------------------------------------------

    /**
     * Get cache driver info
     *
     * @param string $type    Not supported in Redis.
     *                        Only included in order to offer a
     *                        consistent cache API.
     *
     * @return    array
     * @see        Redis::info()
     */
    public abstract function cacheInfo($type = 'user');

    // ------------------------------------------------------------------------

    /**
     * Check if Redis driver is supported
     *
     * @return    bool
     */
    public abstract function isSupported();

    public function ping()
    {
        return true;
    }

    public function remember($key, $ttl, Closure $callback)
    {
        $data = $this->get($key);

        if (is_null($data) && $this->has($key) === false) {
            $data = $callback();
            $this->set($key, $data, $ttl);
        }

        return $data;
    }

    public function rememberForever($key, Closure $callback)
    {
        return $this->remember($key, null, $callback);
    }


    public function sear($key, Closure $callback)
    {
        return $this->remember($key, null, $callback);
    }


    public function put($key, $value, $ttl = null)
    {
        return $this->set($key, $value, $ttl);
    }

    public function pull($key, $default = null)
    {
        $data = $this->get($key, $default);
        $this->delete($key);

        return $data;
    }

    public function forever($key, $value)
    {
        return $this->set($key, $value);
    }

    public function forget($key)
    {
        return $this->delete($key);
    }

    public function getStore()
    {
        return $this;
    }

    protected function prepTtl($ttl)
    {
        if ($ttl instanceof \DateTimeInterface) {
            $ttl = $ttl->getTimestamp() - time();
        } elseif ($ttl instanceof \DateInterval) {
            $ttl = strtotime($ttl->format('Y-m-d H:i:s')) - time();
        } elseif (!is_null($ttl)) {
            $ttl = intval($ttl);
        }

        return $ttl;
    }

    public function flush()
    {
        return $this->clear();
    }

    public function putMany(array $values, $seconds)
    {
        return $this->setMultiple($values, $seconds);
    }

    public function many(array $keys)
    {
        return $this->getMultiple($keys);
    }

    public function getPrefix()
    {
        return $this->key_prefix;
    }
}