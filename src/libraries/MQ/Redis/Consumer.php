<?php

namespace CI\libraries\MQ\Redis;

use CI\libraries\MQ;

class Consumer extends MQ\Consumer
{
    const QUEUE_WORKING_SUFFIX = '_working';
    const MONITOR_TIME_SUFFIX = '_monitor_time';
    const QUEUE_WORKING_SNAPSHOT_SUFFIX = '_snapshot';

    private $queue_name;
    private $working_queue_name;
    private $working_snapshot_queue_name;

    private $monitor_time_key;
    private $timeout = 300;

    public function __construct($config)
    {
        parent::__construct($config);

        if (empty($config['queue'])) {
            show_error('Consumer queue can\'t be empty');
        }

        $this->queue_name = $config['queue'];
        $this->working_queue_name = $this->queue_name . self::QUEUE_WORKING_SUFFIX;
        $this->monitor_time_key = $this->queue_name . self::MONITOR_TIME_SUFFIX;
        $this->working_snapshot_queue_name = $this->queue_name . self::QUEUE_WORKING_SNAPSHOT_SUFFIX;

        if (isset($config['rollback_wait']) && $config['rollback_wait'] >= 120) {
            $this->timeout = (int)ceil($config['rollback_wait'] / 2);
        }
    }

    public function consume()
    {
        $redis = redis($this->server_name);

        if ($this->electMonitor()) {
            $this->rollback();
        }

        if ($this->ack) {
            $msg = $redis->brpoplpush($this->queue_name, $this->working_queue_name, $this->timeout + 10);
        } else {
            $msg = $redis->brPop($this->queue_name, $this->timeout + 10);
        }

        if ($msg !== false) {
            //删除队列中重复的
            $redis->lRem($this->queue_name, $msg, 0);

            $message = json_decode($msg, true);
            if (json_last_error() == JSON_ERROR_NONE) {
                $res = call_user_func($this->callback, $message);
            } else {
                $res = true;
            }

            if ($this->ack && $res === true) {
                $redis->lRem($this->working_queue_name, $msg, 0);
            }
        }
    }

    protected function electMonitor()
    {
        $redis = redis($this->server_name);

        $time = $redis->get($this->monitor_time_key);
        if (intval($time) < time()) {
            $old_time = $redis->getSet($this->monitor_time_key, time() + $this->timeout);
            if ($old_time === $time) {
                return true;
            }
        }

        return false;
    }

    protected function rollback()
    {
        $redis = redis($this->server_name);

        $working = $redis->lRange($this->working_queue_name, 0, -1);
        $working_snapshot = $redis->lRange($this->working_snapshot_queue_name, 0, -1);

        if (empty($working) || empty($working_snapshot)) {
            $failed = [];
        } else {
            $failed = array_intersect($working_snapshot, $working);
        }

        if (!empty($failed)) {
            $failed = array_unique($failed);

            $redis->multi();
            $redis->lPush($this->queue_name, ...$failed);

            foreach ($failed as $v) {
                $redis->lRem($this->working_queue_name, $v, 0);
            }
            $redis->exec();

            $new_working_snapshot = array_diff($working, $failed);
        } else {
            $new_working_snapshot = $working;
        }

        if (!empty($working_snapshot)) {
            $redis->delete($this->working_snapshot_queue_name);
        }

        if (!empty($new_working_snapshot)) {
            $new_working_snapshot = array_unique($new_working_snapshot);

            $redis->lPush($this->working_snapshot_queue_name, ...$new_working_snapshot);
        }
    }
}
