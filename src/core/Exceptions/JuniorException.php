<?php

namespace CI\core\Exceptions;

use CI\core\Error;

class JuniorException extends \Exception
{
    /**
     * @var Error
     */
    protected $err;

    public function __construct(Error $err)
    {
        $this->message = $err->getMsg();
        $this->code = $err->getCode();
        $this->err = $err;
    }

    /**
     * @return Error
     */
    public function getErr()
    {
        return $this->err;
    }

    public function getErrNo()
    {
        return $this->err->getCode();
    }

    public function getErrMsg()
    {
        return $this->err->getMsg();
    }

    public function getErrResults()
    {
        return $this->err->getResults();
    }

    public static function throwSelf(Error $err)
    {
        throw new static($err);
    }
}