<?php

namespace CI\core\Output\Format;

use CI\core\Output\Response;

class Text extends Response
{
    public function __construct(string $data)
    {
        $this->headers['Content-Type'] = 'text/html; charset=utf-8';

        $this->body = $data;
    }
}