<?php

namespace CI\core\Output\Format;

use CI\core\Output\Response;

class Json extends Response
{
    public function __construct($data)
    {
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';

        $this->body = json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}