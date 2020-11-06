<?php

namespace CI\core\Output\Format;

use CI\core\Output\Response;

class Jsonp extends Response
{
    public function __construct($data, string $callback = '')
    {
        if (empty($callback)) {
            $this->headers['Content-Type'] = 'application/json; charset=utf-8';
            $this->headers['Access-Control-Allow-Origin'] = '*';
            $this->headers['Access-Control-Allow-Headers'] = 'Origin, X-Requested-With, Content-Type, Accept';

            $this->body = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            $this->headers['Content-Type'] = 'text/html; charset=utf-8';
            $this->body = sprintf('%s(%s)', $callback, json_encode($data, JSON_UNESCAPED_UNICODE));
        }
    }
}