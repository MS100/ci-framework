<?php

namespace CI\libraries\MQ;

abstract class Consumer
{
    protected $server_name;
    protected $ack = true;
    protected $callback = 'isset';

    public function __construct($config)
    {
        if (empty($config['server'])) {
            show_error('Consumer server can\'t be empty');
        }

        $this->server_name = $config['server'];

        if (isset($config['callback'])) {
            $this->setCallback($config['callback']);
        }

        isset($config['ack']) && $this->ack = $config['ack'];
    }

    public function setCallback($callback)
    {
        if (!is_callable($callback)) {
            show_error($callback . ' is not effective callback function');
        }
        $this->callback = $callback;

        return $this;
    }

    abstract public function consume();

}
