<?php

namespace CI\libraries\MQ;


abstract class Producer
{
    protected $server_name;

    public function __construct($config)
    {
        if (empty($config['server'])) {
            show_error('Producer server can\'t be empty');
        }

        $this->server_name = $config['server'];
    }

    abstract public function publish(...$msg);
}
