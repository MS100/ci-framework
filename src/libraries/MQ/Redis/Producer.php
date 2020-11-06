<?php

namespace CI\libraries\MQ\Redis;

use CI\libraries\MQ;

class Producer extends MQ\Producer
{
    private $queue_name;

    public function __construct($config)
    {
        parent::__construct($config);

        if (empty($config['queue'])) {
            show_error('Producer queue can\'t be empty');
        }
        $this->queue_name = $config['queue'];
    }

    public function publish(...$msg)
    {
        $redis = redis($this->server_name);

        if (count($msg) > 1) {
            array_walk(
                $msg,
                function (&$m)
                {
                    $m = json_encode($m);
                }
            );

            $redis->lPush($this->queue_name, ...$msg);
        } else {
            $redis->lPush($this->queue_name, json_encode($msg[0]));
        }
    }
}
