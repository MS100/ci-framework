<?php

namespace CI\core;

use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\ProcessIdProcessor;

class Log
{
    protected static $initialized = false;

    protected static $config
        = [
            'formatters' => [
                'default' => [
                    'class'  => LineFormatter::class,
                    //保证下面的参数顺序与初始化类时相同
                    'format' => "%datetime%  <%channel%>  %level_name%  %extra%  %message%\n",
                    'date'   => 'Y-m-d H:i:s',
                ],
            ],
            'handlers'   => [
                'default' => [
                    'class'      => StreamHandler::class,
                    'formatter'  => 'default',
                    //保证下面的参数顺序与初始化类时相同
                    'stream'     => APP_PATH.'/mono.log',
                    'level'      => Logger::INFO,
                    'bubble'     => true,
                    'permission' => 0666,
                ],
            ],
            'processors' => [
                'default' => [
                    'class' => ProcessIdProcessor::class,
                ],
            ],
            'loggers'    => [
                'default' => [
                    'handlers'   => ['default'],
                    'processors' => ['default'],
                ],
            ],
        ];

    /**
     * @var \Monolog\Formatter\FormatterInterface[]
     */
    protected static $formatters = [];
    /**
     * @var \Monolog\Handler\AbstractHandler[]
     */
    protected static $handlers = [];
    /**
     * @var array
     */
    protected static $processors = [];
    /**
     * @var \Monolog\Logger[]
     */
    protected static $loggers = [];

    /**
     * @var \Monolog\Logger
     */
    protected static $logger;

    protected static function initialize()
    {
        self::$initialized = true;

        self::loadConfig(config_file('log'));
    }

    protected static function loadConfig(array $config)
    {
        if (!empty($config['formatters']) && is_array($config['formatters'])) {
            self::$config['formatters'] = array_merge(
                self::$config['formatters'],
                $config['formatters']
            );
        }
        if (!empty($config['handlers']) && is_array($config['handlers'])) {
            self::$config['handlers'] = array_merge(
                self::$config['handlers'],
                $config['handlers']
            );
        }
        if (!empty($config['processors']) && is_array($config['processors'])) {
            self::$config['processors'] = array_merge(
                self::$config['processors'],
                $config['processors']
            );
        }
        if (!empty($config['loggers']) && is_array($config['loggers'])) {
            self::$config['loggers'] = array_merge(
                self::$config['loggers'],
                $config['loggers']
            );
        }
    }

    /**
     * @param string $name
     *
     * @return \Monolog\Logger
     */
    protected static function getLogger(string $name = 'default')
    {
        if (!isset(self::$loggers[$name])) {
            if (self::$initialized === false) {
                self::initialize();
            }

            if (isset(self::$config['loggers'][$name])) {
                self::$loggers[$name] = new Logger(
                    $name == 'default' ? APP_SOURCE : $name
                );
                if (!empty(self::$config['loggers'][$name]['handlers'])) {
                    self::pushHandlers(
                        self::$loggers[$name],
                        self::$config['loggers'][$name]['handlers']
                    );
                }

                if (!empty(self::$config['loggers'][$name]['processors'])) {
                    self::pushProcessors(
                        self::$loggers[$name],
                        self::$config['loggers'][$name]['processors']
                    );
                }

                unset(self::$config['loggers'][$name]);
            } else {
                self::$loggers[$name] = self::getLogger()->withName($name);
            }
        }

        return self::$loggers[$name];
    }

    /**
     * @param string $name
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    protected static function getHandler(string $name = 'default')
    {
        if (!isset(self::$handlers[$name])) {
            if (isset(self::$config['handlers'][$name])) {
                $class = self::$config['handlers'][$name]['class'];
                $handler = self::$config['handlers'][$name];
                unset($handler['class'], $handler['formatter'], $handler['processor']);

                self::$handlers[$name] = new $class(...array_values($handler));

                if (!empty(self::$config['handlers'][$name]['formatter'])) {
                    self::$handlers[$name]->setFormatter(
                        self::getFormatter(
                            self::$config['handlers'][$name]['formatter']
                        )
                    );
                }

                if (!empty(self::$config['handlers'][$name]['processors'])) {
                    self::pushProcessors(
                        self::$handlers[$name],
                        self::$config['handlers'][$name]['processors']
                    );
                }

                unset(self::$config['handlers'][$name]);
            } else {
                self::$handlers[$name] = self::getHandler();
            }
        }

        return self::$handlers[$name];
    }

    /**
     * @param string $name
     *
     * @return \Monolog\Formatter\FormatterInterface
     */
    protected static function getFormatter(string $name = 'default')
    {
        if (!isset(self::$formatters[$name])) {
            if (isset(self::$config['formatters'][$name])) {
                $class = self::$config['formatters'][$name]['class'];
                unset(self::$config['formatters'][$name]['class']);

                self::$formatters[$name] = new $class(
                    ...
                    array_values(self::$config['formatters'][$name])
                );

                unset(self::$config['formatters'][$name]);
            } else {
                self::$formatters[$name] = self::getFormatter();
            }
        }

        return self::$formatters[$name];
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    protected static function getProcessor(string $name = 'default')
    {
        if (!isset(self::$processors[$name])) {
            if (isset(self::$config['processors'][$name])) {
                if (isset(self::$config['processors'][$name]['class'])) {
                    $class = self::$config['processors'][$name]['class'];
                    unset(self::$config['processors'][$name]['class']);

                    self::$processors[$name] = new $class(
                        ...
                        array_values(self::$config['processors'][$name])
                    );
                } elseif (isset(self::$config['processors'][$name]['function'])) {
                    self::$processors[$name]
                        = self::$config['processors'][$name]['function'];
                } else {
                    self::$processors[$name] = self::getProcessor();
                }

                unset(self::$config['processors'][$name]);
            } else {
                self::$processors[$name] = self::getProcessor();
            }
        }

        return self::$processors[$name];
    }

    protected static function pushHandlers(Logger $object, $handlers)
    {
        $handlers = array_reverse($handlers);

        foreach ($handlers as $handler) {
            $object->pushHandler(self::getHandler($handler));
        }
    }

    protected static function pushProcessors($object, $processors)
    {
        if ($object instanceof Logger || $object instanceof AbstractHandler) {
            $processors = array_reverse($processors);

            foreach ($processors as $processor) {
                $object->pushProcessor(self::getProcessor($processor));
            }
        }
    }

    /**
     * @param string $level
     * @param mixed  $msg
     * @param array  $context
     *
     * @return bool
     */
    public static function log(string $level, $msg, array $context = [])
    {
        $level = strtolower($level);
        if (!is_string($msg) && !is_numeric($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }

        switch ($level) {
            case LogLevel::EMERGENCY:
                self::getLogger()->emergency($msg, $context);
                break;
            case LogLevel::ALERT:
                self::getLogger()->alert($msg, $context);
                break;
            case LogLevel::CRITICAL:
            case 'fatal':
                self::getLogger()->critical($msg, $context);
                break;
            case LogLevel::ERROR:
                self::getLogger()->error($msg, $context);
                break;
            case 'warn':
            case LogLevel::WARNING:
                self::getLogger()->warning($msg, $context);
                break;
            case LogLevel::NOTICE:
                self::getLogger()->notice($msg, $context);
                break;
            case LogLevel::INFO:
                self::getLogger()->info($msg, $context);
                break;
            case LogLevel::DEBUG:
                self::getLogger()->debug($msg, $context);
                break;
            default:
                self::getLogger($level)->info($msg, $context);
                break;
        }

        return true;
    }

    public static function trace($depth)
    {
        if (is_numeric($depth)) {
            $depth = intval($depth) > 0 ? intval($depth) + 2 : 0;

            $call_info = backtrace($depth);
            array_shift($call_info);
            array_shift($call_info);
        } else {
            $call_info = $depth;
        }
        self::getLogger('trace')->debug(
            json_encode($call_info, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function emergency($message, array $context = array())
    {
        self::log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function alert($message, array $context = array())
    {
        self::log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function critical($message, array $context = array())
    {
        self::log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function error($message, array $context = array())
    {
        self::log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function warning($message, array $context = array())
    {
        self::log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function notice($message, array $context = array())
    {
        self::log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function info($message, array $context = array())
    {
        self::log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public static function debug($message, array $context = array())
    {
        self::log(LogLevel::DEBUG, $message, $context);
    }
}
