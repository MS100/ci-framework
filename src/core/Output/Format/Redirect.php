<?php

namespace CI\core\Output\Format;

use CI\core\Output\Response;

class Redirect extends Response
{
    public function __construct($url, $code = 302)
    {
        $this->headers['Location'] = $url;
        $this->body = '';
    }
}