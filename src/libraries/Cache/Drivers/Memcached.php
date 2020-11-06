<?php

namespace CI\libraries\Cache\Drivers;

use CI\libraries\Cache\Driver;

class Memcached extends Driver
{

    /**
     * Holds the memcached object
     *
     * @var \Memcached
     */
    protected $_memcached;

    /**
     * Memcached configuration
     *
     * @var array
     */
    protected $_config
        = [
            'default' => [
                'host'   => '127.0.0.1',
                'port'   => 11211,
                'weight' => 1,
            ],
        ];

    // ------------------------------------------------------------------------

    /**
     * Class constructor
     * Setup Memcache(d)
     *
     * @param array  $config
     * @param string $key_prefix
     */
    public function __construct(array $config, $key_prefix)
    {
        if (!$this->isSupported()) {
            log_message(
                'critical',
                'Cache: Failed to create Memcached object; extension not loaded?'
            );

            return;
        }

        parent::__construct($config, $key_prefix);

        $defaults = $this->_config['default'];
        $this->_config = $config;

        $this->_memcached = new \Memcached();
        (string)$this->key_prefix !== ''
        && $this->_memcached->setOption(
            \Memcached::OPT_PREFIX_KEY,
            (string)$this->key_prefix
        );
        $this->_memcached->setOptions(
            [
                \Memcached::OPT_TCP_NODELAY     => true,
                \Memcached::OPT_NO_BLOCK        => false,
                //此项为ture会造成poll超时时间不准
                \Memcached::OPT_CONNECT_TIMEOUT => 200,
                //连接超时时间，默认是4000
                \Memcached::OPT_POLL_TIMEOUT    => 250,
                //连接阻塞时操作的超市时间，默认是5000
                //如果连接超时或poll超时有一个设成1秒，则连接阻塞时总超时间会大大加长，原因未知，此时no_block值不影响poll超时不准
            ]
        );

        foreach ($this->_config as $cache_server) {
            isset($cache_server['hostname']) OR
            $cache_server['hostname'] = $defaults['host'];
            isset($cache_server['port']) OR
            $cache_server['port'] = $defaults['port'];
            isset($cache_server['weight']) OR
            $cache_server['weight'] = $defaults['weight'];

            $this->_memcached->addServer(
                $cache_server['hostname'],
                $cache_server['port'],
                $cache_server['weight']
            );
        }
    }

    // ------------------------------------------------------------------------

    /**
     * 转码key，防止不符合规则的key产生错误，例如带空格、制表符、换行符
     *
     * @param $key
     *
     * @return string
     */
    private function encodeKey($key)
    {
        return base64_encode($key);
    }

    // ------------------------------------------------------------------------

    public function has($key)
    {
        $this->_memcached->get($this->encodeKey($key));

        return $this->_memcached->getResultCode() !== \Memcached::RES_NOTFOUND;
    }

    // ------------------------------------------------------------------------

    public function get($key, $default = null)
    {
        $res = $this->_memcached->get($this->encodeKey($key));

        return $this->_memcached->getResultCode() === \Memcached::RES_NOTFOUND
            ? $default
            : $res;
    }

    // ------------------------------------------------------------------------

    public function getMultiple($keys, $default = null)
    {
        $encode_keys = [];
        foreach ($keys as $key) {
            $encode_keys[$key] = $this->encodeKey($key);
        }

        $data = $this->_memcached->getMulti($encode_keys);

        $res = [];
        foreach ($encode_keys as $key => $encode_key) {
            $res[$key] = $data[$encode_key] ?? $default;
        }

        return $res;
    }

    // ------------------------------------------------------------------------

    public function add($key, $data, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl > 2592000) {
            $ttl += time();
        }

        return (bool)$this->_memcached->add(
            $this->encodeKey($key),
            $data,
            $ttl
        );
    }

    // ------------------------------------------------------------------------

    public function set($key, $data, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->delete($key);
        } elseif ($ttl > 2592000) {
            $ttl += time();
        }

        return (bool)$this->_memcached->set(
            $this->encodeKey($key),
            $data,
            $ttl
        );
    }

    // ------------------------------------------------------------------------

    public function replace($key, $data, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->delete($key);
        } elseif ($ttl > 2592000) {
            $ttl += time();
        }

        return (bool)$this->_memcached->replace(
            $this->encodeKey($key),
            $data,
            $ttl
        );
    }

    // ------------------------------------------------------------------------

    public function setMultiple($values, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->deleteMultiple(array_keys($values));
        } elseif ($ttl > 2592000) {
            $ttl += time();
        }

        $new_data = [];
        foreach ($values as $key => $value) {
            $new_data[$this->encodeKey($key)] = $value;
        }

        return (bool)@$this->_memcached->setMulti($new_data, $ttl);
    }

    // ------------------------------------------------------------------------

    public function delete($key)
    {
        return (bool)$this->_memcached->delete($this->encodeKey($key));
    }

    // ------------------------------------------------------------------------

    public function deleteMultiple($keys)
    {
        foreach ($keys as $k => $key) {
            $keys[$k] = $this->encodeKey($key);
        }

        $this->_memcached->deleteMulti($keys);
        $res = $this->_memcached->getResultCode();

        return $res === \Memcached::RES_SUCCESS
            || $res === \Memcached::RES_NOTFOUND;
    }

    // ------------------------------------------------------------------------

    public function increment($key, $value = 1)
    {
        $encode_key = $this->encodeKey($key);
        $res = $this->_memcached->increment($encode_key, $value);
        if ($res === false) {
            $res = $value;
            $this->_memcached->set($encode_key, $res);
        }

        return $res;
    }

    // ------------------------------------------------------------------------

    public function decrement($key, $value = 1)
    {
        $encode_key = $this->encodeKey($key);
        $res = $this->_memcached->decrement($encode_key, $value);
        if ($res === false) {
            $res = -$value;
            $this->_memcached->set($encode_key, $res);
        }

        return $res;
    }

    // ------------------------------------------------------------------------

    /**
     * Clean the Cache
     *
     * @return    bool    false on failure/true on success
     */
    /*public function clean()
    {
        return $this->_memcached->flush();
    }*/

    // ------------------------------------------------------------------------

    /**
     * Cache Info
     *
     * @param null $type
     *
     * @return    mixed    array on success, false on failure
     */
    public function cacheInfo($type = null)
    {
        return $this->_memcached->getStats();
    }


    // ------------------------------------------------------------------------

    /**
     * Is supported
     * Returns FALSE if memcached is not supported on the system.
     * If it is, we setup the memcached object & return TRUE
     *
     * @return    bool
     */
    public function isSupported()
    {
        return extension_loaded('memcached');
    }

    // ------------------------------------------------------------------------

    /**
     * Class destructor
     * Closes the connection to Memcache(d) if present.
     *
     * @return    void
     */
    public function __destruct()
    {
        if (method_exists($this->_memcached, 'quit')) {
            $this->_memcached->quit();
        }

        $this->_memcached = null;
    }
}
