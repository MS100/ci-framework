<?php

namespace CI\core;

abstract class Controller
{
    public function __construct()
    {
        log_message('debug', 'Controller Class Initialized');
    }

    final public function format(string $format = '')
    {
        return ci()->format($format);
    }
}
