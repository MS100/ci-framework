<?php

namespace CI\libraries\Cache\Drivers;

use CI\libraries\Cache\Driver;

class File extends Driver
{

    /**
     * Directory in which to save cache files
     *
     * @var string
     */
    protected $_cache_path;

    /**
     * Initialize file-based cache
     *
     * @param array $config
     */
    public function __construct(array $config, $key_prefix)
    {
        parent::__construct($config, $key_prefix);

        $CI =& get_instance();
        $CI->load->helper('file');
        $this->_cache_path = empty($config['cache_path']) ? APP_PATH
            .'cache/' : $config['cache_path'];
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
        return $this->key_prefix.base64_encode($key);
    }

    // ------------------------------------------------------------------------

    public function has($key)
    {
        $key = $this->encodeKey($key);

        if (!is_file($this->_cache_path.$key)) {
            return false;
        }

        $data = unserialize(file_get_contents($this->_cache_path.$key));

        if (!is_array($data)
            || !array_key_exists('ttl', $data)
            || !array_key_exists('time', $data)
            || !array_key_exists('data', $data)
        ) {
            return false;
        }

        if ($data['ttl'] > 0 && time() > $data['time'] + $data['ttl']) {
            unlink($this->_cache_path.$key);

            return false;
        }

        return true;
    }

    // ------------------------------------------------------------------------

    public function get($key, $default = null)
    {
        $key = $this->encodeKey($key);

        if (!is_file($this->_cache_path.$key)) {
            return $default;
        }

        $data = unserialize(file_get_contents($this->_cache_path.$key));

        if (!is_array($data)
            || !array_key_exists('ttl', $data)
            || !array_key_exists('time', $data)
            || !array_key_exists('data', $data)
        ) {
            return $default;
        }

        if ($data['ttl'] > 0 && time() > $data['time'] + $data['ttl']) {
            unlink($this->_cache_path.$key);

            return $default;
        }

        return $data['data'];
    }

    // ------------------------------------------------------------------------

    public function add($key, $data, $ttl = null)
    {
        if ($this->has($key) === false) {
            return $this->set($key, $data, $ttl);
        } else {
            return false;
        }
    }

    // ------------------------------------------------------------------------

    public function set($key, $data, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->delete($key);
        }

        $key = $this->encodeKey($key);
        $contents = [
            'time' => time(),
            'ttl'  => $ttl,
            'data' => $data,
        ];

        if (write_file($this->_cache_path.$key, serialize($contents))) {
            chmod($this->_cache_path.$key, 0640);

            return true;
        }

        return false;
    }

    // ------------------------------------------------------------------------

    public function replace($key, $data, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->delete($key);
        } elseif ($this->has($key) === false) {
            return false;
        } else {
            return $this->set($key, $data, $ttl);
        }
    }

    // ------------------------------------------------------------------------

    public function delete($key)
    {
        $key = $this->encodeKey($key);

        return is_file($this->_cache_path.$key) ? unlink(
            $this->_cache_path.$key
        ) : false;
    }

    // ------------------------------------------------------------------------

    public function increment($key, $value = 1)
    {
        $data = $this->get($key, false);

        if ($data === false) {
            $data = ['data' => 0, 'ttl' => 0];
        }

        $new_value = intval($data['data']) + $value;

        return $this->set($key, $new_value, $data['ttl'])
            ? $new_value
            : false;
    }

    // ------------------------------------------------------------------------

    public function decrement($key, $value = 1)
    {
        $data = $this->get($key, false);

        if ($data === false) {
            $data = ['data' => 0, 'ttl' => 0];
        }

        $new_value = intval($data['data']) - $value;

        return $this->set($key, $new_value, $data['ttl'])
            ? $new_value
            : false;
    }

    // ------------------------------------------------------------------------

    /**
     * Clean the Cache
     *
     * @return    bool    false on failure/true on success
     */
    /*public function clear()
    {
        return delete_files($this->_cache_path, false, true);
    }*/

    // ------------------------------------------------------------------------

    /**
     * Cache Info
     * Not supported by file-based caching
     *
     * @param null $type
     *
     * @return    mixed    FALSE
     */
    public function cacheInfo($type = null)
    {
        return get_dir_file_info($this->_cache_path);
    }

    // ------------------------------------------------------------------------

    /**
     * Is supported
     * In the file driver, check to see that the cache directory is indeed writable
     *
     * @return    bool
     */
    public function isSupported()
    {
        return is_really_writable($this->_cache_path);
    }

    // ------------------------------------------------------------------------

    public function getMultiple($keys, $default = null)
    {
        $return = [];
        foreach ($keys as $key) {
            $return[$key] = $this->get($key, $default);
        }

        return $return;
    }

    // ------------------------------------------------------------------------

    public function setMultiple($values, $ttl = null)
    {
        $ttl = $this->prepTtl($ttl);

        if ($ttl === 0 || $ttl < 0) {
            return $this->deleteMultiple(array_keys($values));
        }

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }


    // ------------------------------------------------------------------------

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

}
