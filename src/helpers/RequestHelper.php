<?php

namespace CI\helpers;

class RequestHelper
{

    public static function reqFromPhone()
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        return (bool)preg_match(
            '/(Android|webOS|iPhone|iPod|iPAD|BlackBerry|Nexus|Optimus|MDPI|HiDPI|Samsung|wp7|wp8|surface|nokia)/i',
            $_SERVER['HTTP_USER_AGENT']
        );
    }

    public static function reqFromWechat()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return strpos($user_agent, 'MicroMessenger') !== false;
    }
}