<?php

namespace CI\helpers;

class StringHelper
{
    /**
     * 密码规则
     *
     * @param $password
     * @param $salt
     *
     * @return string
     */
    public static function passwordHash($password, $salt)
    {
        return md5(strtolower($password).$salt);
    }

    public static function getRandStr($len_min, $len_max = null)
    {
        $len = isset($len_max) ? mt_rand($len_min, $len_max) : $len_min;
        $str = '';
        $str_pol
            = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < $len; $i++) {
            $str .= $str_pol[mt_rand(0, $max)];
        }

        return $str;
    }

    public static function getRandNum($length)
    {
        $num = mt_rand(0, pow(10, $length) - 1);

        return str_pad((string)$num, $length, '0', STR_PAD_LEFT);
    }


    public static function strHasChinese($str)
    {
        return strlen($str) != mb_strlen($str, 'UTF-8');
    }

}