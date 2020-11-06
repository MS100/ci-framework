<?php

namespace CI\core\Exceptions;

use CI\core\Error;
use Psr\Log\LogLevel;

class SeniorException extends JuniorException
{
    protected $log_level;
    /**
     * @var Error
     */
    protected $err;

    public function __construct($log_msg, Error $err, $log_level = LogLevel::NOTICE)
    {
        parent::__construct($err);
        $this->message = is_string($log_msg) ? $log_msg : json_encode($log_msg, JSON_UNESCAPED_UNICODE);
        $this->log_level = $log_level;
    }

    public function getLogMsg()
    {
        return $this->message;
    }

    public function getLogLevel()
    {
        return $this->log_level;
    }
}
