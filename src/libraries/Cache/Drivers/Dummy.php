<?php

namespace CI\libraries\Cache\Drivers;

use CI\libraries\Cache\Driver;

class Dummy extends Driver
{
    public function has($key)
    {
        return false;
    }

    // ------------------------------------------------------------------------
    public function get($key, $default = null)
    {
        return $default;
    }

    public function getMultiple($keys, $default = null)
    {
        return array_fill_keys($keys, $default);
    }

    // ------------------------------------------------------------------------

    public function add($key, $data, $ttl = null)
    {
        return true;
    }

    public function set($key, $data, $ttl = null)
    {
        return true;
    }

    // ------------------------------------------------------------------------

    public function replace($key, $data, $ttl = null)
    {
        return false;
    }

    public function setMultiple($values, $ttl = null)
    {
        return true;
    }
    // ------------------------------------------------------------------------


    public function delete($key)
    {
        return true;
    }

    public function deleteMultiple($keys)
    {
        return true;
    }
    // ------------------------------------------------------------------------

    public function increment($key, $value = 1)
    {
        return $value;
    }

    // ------------------------------------------------------------------------

    public function decrement($key, $value = 1)
    {
        return -$value;
    }

    // ------------------------------------------------------------------------

    public function cacheInfo($type = null)
    {
        return false;
    }

    // ------------------------------------------------------------------------

    public function isSupported()
    {
        return true;
    }

}
