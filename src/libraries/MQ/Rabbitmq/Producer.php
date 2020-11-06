<?php

namespace CI\libraries\MQ\Rabbitmq;

use CI\libraries\MQ;
use CI\libraries\Rabbitmq;

class Producer extends MQ\Producer
{
    /**
     * @var \AMQPExchange
     */
    private $exchange;
    private $routing_key = '';

    public function __construct($config)
    {
        parent::__construct($config);

        if (empty($config['exchange']) && empty($config['routing_key'])) {
            show_error('Producer exchange and routing_key can\'t be empty at the same time');
        }

        isset($config['routing_key']) && $this->routing_key = $config['routing_key'];

        $this->setExchange($config['exchange'] ?? '');
    }

    public function publish(...$msg)
    {
        if (!$this->exchange->getChannel()->isConnected()) {
            $exchange_name = $this->exchange->getName();
            $this->setExchange($exchange_name);
        }


        if (count($msg) > 1) {
            foreach ($msg as $m) {
                $this->exchange->publish(json_encode($m), $this->routing_key);
            }
        } else {
            $this->exchange->publish(json_encode($msg[0]), $this->routing_key);
        }
    }

    protected function setExchange(string $exchange_name)
    {
        $channel = new \AMQPChannel(Rabbitmq::load($this->server_name));
        $this->exchange = new \AMQPExchange($channel);
        $this->exchange->setName($exchange_name);
    }

    public function __destruct()
    {
        $this->exchange->getChannel()->close();
    }
}
