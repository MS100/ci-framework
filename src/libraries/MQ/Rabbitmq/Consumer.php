<?php

namespace CI\libraries\MQ\Rabbitmq;

use CI\libraries\MQ;
use CI\libraries\Rabbitmq;

class Consumer extends MQ\Consumer
{
    /**
     * @var \AMQPQueue
     */
    private $queue;

    public function __construct($config)
    {
        parent::__construct($config);

        if (empty($config['queue'])) {
            show_error('Consumer queue can\'t be empty');
        }

        $this->setQueue($config['queue']);
    }

    public function consume()
    {
        try {
            if (!$this->queue->getChannel()->isConnected()) {
                $queue_name = $this->queue->getName();
                $this->setQueue($queue_name);
            }

            $this->queue->consume(
                function (\AMQPEnvelope $msg) {
                    $body = json_decode($msg->getBody(), true);
                    if (json_last_error() == JSON_ERROR_NONE) {
                        $res = call_user_func($this->callback, $body);
                    } else {
                        $res = true;
                    }

                    if ($this->ack) {
                        if ($res === true) {
                            $this->queue->ack($msg->getDeliveryTag());
                        } else {
                            $this->queue->nack($msg->getDeliveryTag(), AMQP_REQUEUE);
                            usleep(50000);
                        }
                    }
                }
            );
        } catch (\Throwable $e) {
            //可能是rabbitmq挂了
            log_message('alert', $e->getMessage());
            $this->closeChannel();
            sleep(15);
        }
    }

    protected function setQueue(string $queue_name)
    {
        $channel = new \AMQPChannel(Rabbitmq::load($this->server_name));
        echo $channel->getPrefetchCount(), $channel->getPrefetchSize();
        $this->queue = new \AMQPQueue($channel);
        $this->queue->setName($queue_name);
    }

    protected function closeChannel()
    {
        try {
            $this->queue->getChannel()->close();
        } catch (\Throwable $e) {
        }
    }

    public function __destruct()
    {
        $this->closeChannel();
    }
}
