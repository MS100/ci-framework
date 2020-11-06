<?php

namespace CI\core;

use CI\core\Output\Response;
use CI\core\Output\Format\Json;
use CL\Common\Error;

class Output
{
    protected const CODE_FIELD_NAME = 'err_no';
    protected const MSG_FIELD_NAME = 'err_msg';
    protected const DATA_FIELD_NAME = 'results';
    protected const CODE_DEFAULT = '0';

    protected $format = Json::class;

    protected $headers = [];
    protected $format_body_func;

    protected $code = self::CODE_DEFAULT;
    /**
     * @var Response
     */
    protected $response;

    public function format(string $format = '')
    {
        if (empty($format)) {
            return $this->format;
        }

        if (!in_array($format, ci()->getAllowResponseFormat())) {
            show_error('Not allow this format');
        }
        return $this->format = $format;
    }

    /**
     * @param $data
     *
     * @return Response
     */
    public function render($data)
    {
        if ($data instanceof Response) {
            $this->response = $data;
        } else {
            $this->newResponse($data);
        }

        return $this->response;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        if (is_null($this->response)) {
            $this->newResponse();
        }

        return $this->response;
    }

    protected function newResponse($data = null)
    {
        $data = $this->makeStructure($data);

        $this->response = new $this->format($data);
    }

    public function isSuccess()
    {
        return $this->code === self::CODE_DEFAULT;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function setHeader(string $name, string $value)
    {
        $this->headers[ucwords(strtolower($name), '-')] = $value;
    }

    public function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function flushHeaders()
    {
        $this->headers = [];
    }

    final public function makeStructure($param)
    {
        if ($param instanceof Error) {
            $this->code = $param->getCode();
            $data = [
                self::CODE_FIELD_NAME => $param->getCode(),
                self::MSG_FIELD_NAME => $param->getMsg(),
                self::DATA_FIELD_NAME => $param->getResults(),
            ];
        } else {
            if (is_bool($param)) {
                $param = ['success' => $param];
            }
            $data = [
                self::CODE_FIELD_NAME => self::CODE_DEFAULT,
                self::MSG_FIELD_NAME => '',
                self::DATA_FIELD_NAME => $param,
            ];
        }

        if (is_callable($this->format_body_func)) {
            $data = ($this->format_body_func)($data);
        }

        return $data;
    }

    public function setMakeStructureCallback(callable $func)
    {
        $this->format_body_func = $func;
    }

}
