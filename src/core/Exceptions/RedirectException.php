<?php

namespace CI\core\Exceptions;

class RedirectException extends \Exception
{
    protected $url;

    public function __construct(string $url, int $code = 302)
    {
        $this->url = $url;
        $this->message = 'Need Redirect';
        $this->code = $code;
    }

    public function getUrl()
    {
        return $this->url;
    }
}