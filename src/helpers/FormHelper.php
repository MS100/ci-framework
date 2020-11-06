<?php

namespace CI\helpers;


class FormHelper
{
    public static function getCsrfTokenName()
    {
        $SEC = load_class('Security', 'core');

        return $SEC->get_csrf_token_name();
    }

    public static function getCsrfHash()
    {
        $SEC = load_class('Security', 'core');

        return $SEC->get_csrf_hash();
    }
}