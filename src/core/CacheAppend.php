<?php

namespace CI\core;

//use CI\models\CacheAppendModel;

class CacheAppend
{
    protected static $cache_append = [];

    public static function load()
    {
        //self::$cache_append = CacheAppendModel::load();
    }

    public static function getAppend($key)
    {
        return isset(self::$cache_append[$key]) ? self::$cache_append[$key] . '_' : '';
    }
}