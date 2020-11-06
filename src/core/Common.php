<?php

use CI\core\Exceptions\JuniorException;
use CI\core\Exceptions\SeniorException;
use CI\core\Exceptions\RedirectException;
use CI\core\Output\Format\Redirect;
use CL\Common\Error;
use CI\core\Exceptions;
use Psr\Log\LogLevel;
use Illuminate\Container\Container;

/**
 * Reference to the \CI\core\CI method.
 * Returns current CI instance object
 *
 * @return object
 */
function &get_instance()
{
    return \CI\core\CI::getInstance();
}

function &ci()
{
    return \CI\core\CI::getInstance();
}

/**
 * Class registry
 * This function acts as a singleton. If the requested class does not
 * exist it is instantiated and set to a static variable. If it has
 * previously been instantiated the variable is returned.
 *
 * @param string    the class name being requested
 * @param string    the directory where the class should be found
 * @param mixed     an optional argument to pass to the class constructor
 *
 * @return    object
 */
function &load_class($class, $directory = 'core', $param = null)
{
    $class = strtolower($class);
    if (!in_array(
        $class,
        [
            'benchmark',
            'config',
            'exceptions',
            'hooks',
            'input',
            'output',
            'lang',
            'loader',
            'router',
            'security',
            'uri',
            'utf8',
        ]
    )
    ) {
        show_error('不允许使用load_class加载 '.$class.' 类', 500);
    }

    if ($class == 'exceptions') {
        return new Exceptions();
    }

    $CI = ci();

    // Does the class exist?  If so, we're done...
    if (isset($CI->$class)) {
        return $CI->$class;
    }

    $name = '\\CI\\'.$directory.'\\'.ucfirst($class);

    // Did we find the class?
    if (!class_exists($name)) {
        // Note: We use exit() rather than show_error() in order to avoid a
        // self-referencing loop with the Exceptions class
        show_error('Unable to locate the specified class: '.$class.'.php', 503);
        exit(5); // EXIT_UNK_CLASS
    }

    // Keep track of what we just loaded
    //is_loaded($class);

    $CI->$class = isset($param)
        ? new $name($param)
        : new $name();

    return $CI->$class;
}

/**
 * Keeps track of which libraries have been loaded. This function is
 * called by the load_class() function above
 *
 * @param string
 *
 * @return    array
 */
function &is_loaded($class = '')
{
    exit('此方法已停止使用！');
    /*static $_is_loaded = [];

    if ($class !== '') {
        $_is_loaded[strtolower($class)] = $class;
    }

    return $_is_loaded;*/
}

/**
 * Loads the main config.php file
 * This function lets us grab the config file even if the Config class
 * hasn't been instantiated yet
 *
 * @param array
 *
 * @return    array
 */
function &get_config()
{
    //这个方法因为在框架里调用，所以&不能去掉，先付给变量再返回是为了取消引用关系
    $config = ci()->config->config;

    return $config;
}

/**
 * @param      $file
 * @param bool $use_sections
 * @param bool $fail_gracefully
 *
 * @return bool
 */
function config_load($file, $use_sections = false, $fail_gracefully = false)
{
    return ci()->config->load($file, $use_sections, $fail_gracefully);
}

function config($key = null, $default = null)
{
    if (is_null($key)) {
        return app('config');
    }

    if (is_array($key)) {
        return app('config')->set($key);
    }

    return app('config')->get($key, $default);
}

function app($abstract = null, array $parameters = [])
{
    if (is_null($abstract)) {
        return Container::getInstance();
    }

    return Container::getInstance()->make($abstract, $parameters);
}

/**
 * Returns the specified config item
 *
 * @param string $item
 * @param string $default
 *
 * @return    mixed
 */
function config_item(string $item, $default = null)
{
    return ci()->config->item($item) ?? value($default);
}

/**
 * @param string $item
 * @param mixed  $value
 */
function config_set_item(string $item, $value)
{
    ci()->config->set_item($item, $value);
}

/**
 * @param string $file
 * @param mixed  $dirs
 *
 * @return array
 */
function config_file(string $file, $dirs = APP_PATH)
{
    return load_config_file($file, $dirs);
}

/**
 * @param string $err_key
 * @param array  $results
 *
 * @return Error
 */
function err(string $err_key, array $results = [])
{
    return Error::getInstance($err_key, $results);
}


/**
 * Returns the MIME types array from config/mimes.php
 *
 * @return    array
 */
function &get_mimes()
{
    static $mimes;
    if (!isset($mimes)) {
        $mimes = config_file('mimes', CI_PATH);
    }

    //不将静态变量返回
    $res = $mimes;

    return $res;
}

function get_mime(string $filename)
{
    $mimes =& get_mimes();
    $ext = file_ext($filename);

    if (isset($mimes[$ext])) {
        $mime = is_array($mimes[$ext]) ? $mimes[$ext][0] : $mimes[$ext];
    } else {
        $mime = 'application/octet-stream';
    }

    return $mime;
}

/**
 * Error Logging Interface
 * We use this as a simple mechanism to access the logging
 * class and send messages to be logged.
 *
 * @param string    the error level: 'error', 'debug' or 'info'
 * @param mixed    the error message
 * @param array     the error context
 *
 * @return    void
 */
function log_message(string $level, $message, array $context = [])
{
    \CI\core\Log::log($level, $message, $context);
}


/**
 * Is CLI?
 * Test to see if a request was made from the command line.
 *
 * @return    bool
 */
function is_cli()
{
    return is_run_mode(RM_CLI);
}

function is_run_mode(int $rm)
{
    return defined('RUN_MODE') && (bool)(RUN_MODE & $rm);
}

/**
 * 404 Page Handler
 * This function is similar to the show_error() function above
 * However, instead of the standard error template it displays
 * 404 errors.
 *
 * @param string
 *
 * @throws SeniorException
 */
function show_404(string $page = '')
{
    if (is_run_mode(RM_FPM_WEB | RM_SWOOLE_WEB)) {
        set_status_header(404);
        throw new JuniorException(err('404'));
    } else {
        //$heading = 'Not Found';
        $message = 'The '.($page ?: 'controller/method')
            .' you requested was not found.';

        throw new JuniorException(new Error(404, $message));
    }
}

/**
 * Error Handler
 * This function lets us invoke the exception class and
 * display errors using the standard error template located
 * in application/views/errors/error_general.php
 * This function will send the error page directly to the
 * browser and exit.
 *
 * @param string
 * @param int
 * @param string
 *
 * @throws   SeniorException
 */
function show_error(
    string $message,
    int $status_code = 500,
    string $heading = 'An Error Was Encountered'
) {
    $_error = new Exceptions();
    $_error->show_error($heading, $message, 'error_general', $status_code);
}

/**
 * Exception Handler
 * Sends uncaught exceptions to the logger and displays them
 * only if display_errors is On so that they don't show up in
 * production environments.
 *
 * @param Throwable|Error|Exception|SeniorException|Twig_Error $exception
 *
 * @return    void
 */
function _exception_handler(\Throwable $exception)
{
    //dispose_exception($exception, 'critical');

    $_error = new Exceptions();
    $_error->show_exception($exception);
}

/**
 * Dispose Exception
 *
 * @param \Throwable $exception
 * @param string     $log_level
 *
 * @return    \CL\Common\Error
 */
function dispose_exception(\Throwable $exception, $log_level = null)
{
    //echo $exception;exit;
    $message = sprintf(
        'EXCEPTION(%s): %s IN %s(%d)',
        $exception->getCode(),
        $exception->getMessage(),
        trim_app_path($exception->getFile()),
        $exception->getLine()
    );

    if ($exception instanceof SeniorException) {
        log_message($exception->getLogLevel(), $message);
        $err = $exception->getErr();
    } elseif ($exception instanceof JuniorException) {
        $err = $exception->getErr();
    } elseif ($exception instanceof Twig_Error) {
        $source = $exception->getSourceContext();
        $message = sprintf(
            'TWIG EXCEPTION(%s): %s IN %s(%d)',
            $exception->getCode(),
            $exception->getRawMessage(),
            is_null($source) ? '' : $source->getPath(),
            $exception->getTemplateLine()
        );
        $err = new Error($exception->getCode(), $message);
        log_message($log_level ?? LogLevel::CRITICAL, $message);
    } elseif ($exception instanceof RedirectException) {
        $err = new Redirect($exception->getUrl(), $exception->getCode());
    } else {
        $err = err($exception->getCode() ?: '-1');
        log_message($log_level ?? LogLevel::CRITICAL, $message);
    }

    return $err;
}

/**
 * Error Handler
 * This is the custom error handler that is declared at the(relative)
 * top of CodeIgniter . php . The main reason we use this is to permit
 * PHP errors to be logged in our own log files since the user may
 * not have access to server logs . Since this function effectively
 * intercepts PHP errors, however, we also need to display errors
 * based on the current error_reporting level .
 * We do that with the use of a PHP error template .
 *
 * @param int    $severity
 * @param string $message
 * @param string $filepath
 * @param int    $line
 *
 * @return void
 */
function _error_handler(
    int $severity,
    string $message,
    string $filepath,
    int $line
) {
    //$is_error = (((E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR) & $severity) === $severity);

    // Should we ignore the error? We'll get the current error_reporting
    // level and add its bits with the severity bits to find out.
    if (($severity & error_reporting()) !== $severity) {
        //log_message('info', sprintf('[ignore error] %s: %s IN %s(%s)', $severity, $message, $filepath, $line));
        return;
    }

    // When an error occurred, set the status header to '500 Internal Server Error'
    // to indicate to the client something went wrong.
    // This can't be done within the $_error->show_php_error method because
    // it is only called when the display_errors flag is set (which isn't usually
    // the case in a production environment) or when errors are ignored because
    // they are above the error_reporting threshold.
    /*if ($is_error) {
        set_status_header(500);
    }*/

    //log_message('critical', sprintf('PHP ERROR(%s): %s IN %s(%d)', $severity, $message, trim_path($filepath), $line));
    $_error = new Exceptions();
    //$_error->log_exception($severity, $message, $filepath, $line);

    // Should we display the error?
    //if(str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors'))){
    $_error->show_php_error($severity, $message, $filepath, $line);
    //}

    // If the error is fatal, the execution of the script should be stopped because
    // errors can't be recovered from. Halting the script conforms with PHP's
    // default error handling. See http://www.php.net/manual/en/errorfunc.constants.php
    //if($is_error){
    //exit(1); // EXIT_ERROR
    //}
}


/**
 * @param $db_connection
 *
 * @return \CI\libraries\DB\Drivers\Mongo|\CI_DB_mysqli_driver|\CI_DB_query_builder|\CI_DB_driver
 */
function db(string $db_connection = 'default')
{
    return \CI\libraries\DB\DB::load($db_connection);
}


/**
 * @param string $cache_connection
 *
 * @return \CI\libraries\Cache\Driver
 */
function cache(string $cache_connection = 'default')
{
    return \CI\libraries\Cache\Cache::load($cache_connection);
}

/**
 * @return \Illuminate\Session\Store
 */
function session()
{
    if (!isset(ci()->session)) {
        ci()->session = \CI\libraries\Session\Session::load();
    }

    return ci()->session;
}

/**
 * @param $redis_connection
 *
 * @return Redis
 */
function redis(string $redis_connection = 'default')
{
    return \CI\libraries\Redis::load($redis_connection);
}

/**
 * @param string $producer_connection
 *
 * @return \CI\libraries\MQ\Producer
 */
function mq_producer(string $producer_connection)
{
    return \CI\libraries\MQ\MQ::producer($producer_connection);
}

/**
 * @param string $consumer_connection
 *
 * @return \CI\libraries\MQ\Consumer
 */
function mq_consumer(string $consumer_connection)
{
    return \CI\libraries\MQ\MQ::consumer($consumer_connection);
}


// ------------------------------------------------------------------------

if (!function_exists('set_status_header')) {
    /**
     * Set HTTP Status Header
     *
     * @param int    the status code
     * @param string
     *
     * @return    void
     */
    function set_status_header($code = 200, $text = '')
    {
        if (!is_run_mode(RM_FPM_WEB | RM_SWOOLE_WEB) || headers_sent()) {
            return;
        }

        if (empty($code) OR !is_numeric($code)) {
            show_error('Status codes must be numeric', 500);
        }

        if (empty($text)) {
            is_int($code) OR $code = (int)$code;
            $stati = [
                100 => 'Continue',
                101 => 'Switching Protocols',

                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',

                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',

                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',
                422 => 'Unprocessable Entity',
                426 => 'Upgrade Required',
                428 => 'Precondition Required',
                429 => 'Too Many Requests',
                431 => 'Request Header Fields Too Large',

                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported',
                511 => 'Network Authentication Required',
            ];

            if (isset($stati[$code])) {
                $text = $stati[$code];
            } else {
                show_error(
                    'No status text available. Please check your status code number or supply your own message text.',
                    500
                );
            }
        }

        if (strpos(PHP_SAPI, 'cgi') === 0) {
            header('Status: '.$code.' '.$text, true);

            return;
        }

        $server_protocol = (isset($_SERVER['SERVER_PROTOCOL'])
            && in_array(
                $_SERVER['SERVER_PROTOCOL'],
                ['HTTP/1.0', 'HTTP/1.1', 'HTTP/2'],
                true
            ))
            ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header($server_protocol.' '.$code.' '.$text, true, $code);
    }
}

/**
 * @param string $group
 *
 * @return array|bool
 * @throws \CI\core\Exceptions\FormException
 */
function validate_post($group = '')
{
    return \CI\libraries\FormValidation::validatePost($group);
}

/**
 * @param string $group
 *
 * @return array|bool
 * @throws \CI\core\Exceptions\FormException
 */
function validate_get($group = '')
{
    return \CI\libraries\FormValidation::validateGet($group);
}

function lang($line, $source_file = 'common')
{
    $lang = load_class('Lang', 'core');
    $lang->load($source_file);
    $res = $lang->line($source_file.'_'.$line);

    if ($res === false) {
        return $line;
    } else {
        return $res;
    }
}

function mkdir_recursive(string $pathname, int $mode = 0777)
{
    $temp = [];
    $target = $pathname;
    while ($pathname != DIRECTORY_SEPARATOR && $pathname != '.') {
        if (file_exists($pathname)) {
            if (is_dir($pathname)) {
                break;
            } else {
                return false;
            }
        }
        $temp[] = basename($pathname);
        $pathname = dirname($pathname);
    }

    if (!empty($temp)) {
        mkdir($target, $mode, true);
        for ($i = count($temp) - 1; $i >= 0; $i--) {
            $pathname .= DIRECTORY_SEPARATOR.$temp[$i];
            chmod($pathname, $mode);
        }
    }

    return true;
}