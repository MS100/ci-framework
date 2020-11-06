<?php

namespace CI\libraries\MQ;

class MQ
{
    protected static $producers = [];
    protected static $consumers = [];

    protected static function getProducerConfig(string $name)
    {
        static $configs;
        if (!isset($configs)) {
            $configs = config_file('mq_producer');
        }

        if (!isset($configs[$name])) {
            show_error('You have use an undefined mq producer config (' . $name . ') in your config/mq_producer.php file.');
        }

        return $configs[$name];
    }

    protected static function getConsumerConfig(string $name)
    {
        static $configs;
        if (!isset($configs)) {
            $configs = config_file('mq_consumer');
        }

        if (!isset($configs[$name])) {
            show_error('You have use an undefined mq consumer config (' . $name . ') in your config/mq_consumer.php file.');
        }

        return $configs[$name];
    }


    /**
     * @param string $name
     * @return mixed
     * @throws \CI\core\Exceptions\SeniorException
     */
    public static function producer(string $name)
    {
        if (!isset(self::$producers[$name])) {
            self::$producers[$name] = Factory::load('producer', self::getProducerConfig($name));
        }
        return self::$producers[$name];
    }

    /**
     * @param string $name
     * @return mixed
     */
    public static function consumer(string $name)
    {
        if (!isset(self::$consumers[$name])) {
            self::$consumers[$name] = Factory::load('consumer', self::getConsumerConfig($name));
        }
        return self::$consumers[$name];
    }
}