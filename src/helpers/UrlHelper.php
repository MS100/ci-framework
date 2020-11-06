<?php

namespace CI\helpers;

use CI\core\Exceptions\RedirectException;

class UrlHelper
{
    public static function prepUrl($str = '')
    {
        if ($str === 'http://' OR $str === '' OR $str === '//') {
            return '';
        }

        $url = parse_url($str);

        if (!$url || !isset($url['scheme'])) {
            return 'http://'.ltrim($str, ':/');
        }

        return $str;
    }

    public static function currentUrl($protocol = 'https')
    {
        return $protocol.'://'.$_SERVER['HTTP_HOST'].'/'.ci()->uri->uri_string()
            .'?'.$_SERVER['QUERY_STRING'];
    }


    public static function redirect($uri = '', $method = 'auto', $code = 302)
    {
        if (!preg_match('#^(\w+:)?//#i', $uri)) {
            $uri = site_url($uri);
        }
        throw new RedirectException($uri, $code);
    }
}