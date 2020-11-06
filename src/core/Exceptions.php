<?php

namespace CI\core;

use CI\core\Exceptions\SeniorException;
use CL\Common\Error;
use Psr\Log\LogLevel;

class Exceptions
{

    /**
     * Nesting level of the output buffering mechanism
     *
     * @var    int
     */
    //public $ob_level;

    /**
     * List of available error levels
     *
     * @var    array
     */
    public const LEVELS = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parsing Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Runtime Notice',
    ];

    /**
     * Class constructor
     *
     * @return    void
     */
    public function __construct()
    {
        //$this->ob_level = ob_get_level();
        // Note: Do not log messages from this constructor.
    }

    // --------------------------------------------------------------------

    /**
     * Exception Logger
     * Logs PHP generated error messages
     *
     * @param int $severity Log level
     * @param string $message Error message
     * @param string $filepath File path
     * @param int $line Line number
     *
     * @return    void
     */
    public function log_exception(int $severity, string $message, string $filepath, int $line)
    {
        $severity = self::LEVELS[$severity] ?? $severity;
        log_message('error',
            'Severity: ' . $severity . ' --> ' . $message . ' ' . trim_app_path($filepath) . ' ' . $line);
    }


    // --------------------------------------------------------------------

    /**
     * General Error Page
     * Takes an error message as input (either as a string or an array)
     * and displays it using the specified template.
     *
     * @param string $heading Page heading
     * @param string|string[] $message Error message
     * @param string $template Template name
     * @param int $status_code (default: 500)
     *
     * @return    string    Error page output
     * @throws  \Exception
     */
    function show_error(string $heading, string $message, string $template = 'error_general', int $status_code = 500)
    {
        $status_code = abs($status_code);
        /*if ($status_code < 100) {
            //$exit_status = $status_code + 9; // 9 is EXIT__AUTO_MIN
            $status_code = 500;
        } else {
            //$exit_status = 1; // EXIT_ERROR
        }*/

        if ($status_code >= 400 && $status_code < 500) {
            set_status_header($status_code);
            $log_level = LogLevel::NOTICE;
        } else {
            set_status_header(500);
            $log_level = LogLevel::CRITICAL;
        }

        throw new SeniorException(
            $message,
            new Error(
                $status_code,
                $message,
            /*[
                'header' => $heading,
                'message' => $message,
                'template' => 'error_general',
            ]*/
            ),
            $log_level
        );
    }

    // --------------------------------------------------------------------

    /**
     * @param $exception \Throwable|\Exception|\CI\core\Exceptions\SeniorException $exception
     *
     * @throws \Exception
     */
    public function show_exception(\Throwable $exception)
    {
        $heading = 'Exception';
        $message = sprintf('Exception(%s): %s IN %s(%d)', $exception->getCode(), $exception->getMessage(),
            trim_app_path($exception->getFile()), $exception->getLine());

        log_message(LogLevel::CRITICAL, $message);

        $trace = $exception->getTrace();
        log_message(LogLevel::WARNING, json_encode($trace));

        $this->show_error($heading, $message, 'error_exception');
        //exit(1);
    }

    // --------------------------------------------------------------------

    /**
     * Native PHP error handler
     *
     * @param int $severity Error level
     * @param string $message Error message
     * @param string $filepath File path
     * @param int $line Line number
     *
     * @return    string    Error page output
     * @throws \Exception
     */
    public function show_php_error(int $severity, string $message, string $filepath, int $line)
    {
        /*if (ENVIRONMENT == ENV_PROD) {
            $heading = '代码发生了点意外~';
            $message = '代码发生了点意外~程序员哥哥正在抓紧修复~';
        } else {*/
        $heading = 'PHP ' . (self::LEVELS[$severity] ?? 'ERROR(' . $severity . ')');
        $msg = sprintf('%s: %s IN %s(%d)', $heading, $message, trim_app_path($filepath), $line);
        //}
        log_message('critical', $msg);

        Log::trace(0);

        $this->show_error($heading, $msg, 'error_php');
        //exit(1);
    }

}
