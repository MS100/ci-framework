<?php

namespace CI\core\Output;

abstract class Response
{
    protected $headers = [];
    protected $body = '';

    /*public function setHeader(string $name, string $value)
    {
        $this->headers[ucwords(strtolower($name), '-')] = $value;
    }

    public function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }*/

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function __toString()
    {
        $output_headers = ci()->output->getHeaders();
        if (!empty($output_headers)) {
            foreach ($output_headers as $key => $value) {
                header($key . ': ' . $value);
            }
        }

        foreach ($this->headers as $key => $value) {
            header($key . ': ' . $value);
        }

        return $this->body;
    }

}