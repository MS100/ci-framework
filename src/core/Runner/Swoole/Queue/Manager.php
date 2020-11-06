<?php

namespace CI\core\Runner\Swoole\Queue;

/**
 * Class that handles all the process management
 */
class Manager
{
    /**
     * Default config section name
     */
    const DEFAULT_CONFIG = 'SwooleManager';

    /**
     * Defines job priority limits
     */
    const MIN_PRIORITY = -5;
    const MAX_PRIORITY = 5;

    /**
     * Holds the worker configuration
     */
    private static $config = [];

    /**
     * Boolean value that determines if the running code is the parent or a child
     */
    private static $is_parent = true;

    /**
     * When true, queues will stop look for jobs and the parent process will
     * kill off all running children
     */
    private static $stop_work = false;

    /**
     * When true, master will wait for assist process work over.
     */
    //private static $wait_assist = false;

    /**
     * The array of running child processes
     */
    private static $children = [];

    /**
     * The PID of the running process. Set for parent and child processes
     */
    private static $pid = 0;

    /**
     * The PID of the parent process, when running in the forked helper.
     */
    private static $parent_pid = 0;

    /**
     * The PID of the assist process.
     */
    //private static $assist_pid = 0;

    /**
     * PID file for the parent process
     */
    private static $pid_file = '';

    /**
     * The user to run as
     */
    private static $user = null;

    /**
     * Maximum job iterations per worker
     */
    private static $max_request = 2000;

    /**
     * Number of times this worker has run a job
     */
    private static $job_execution_count = 0;

    /**
     * List of functions available for work
     */
    private static $functions = [];

    /**
     * Parses the command line options
     */
    private static function getOpt()
    {
        $opts = getopt('ac:dD:h:Hl:p:P:s:u:v:r:t:Z');

        if (isset($opts['H'])) {
            self::showHelp();
        }

        if (isset($opts['c'])) {
            $config_file = $opts['c'];
        }

        if (isset($config_file)) {
            if (file_exists($config_file)) {
                self::parseConfig($config_file);
            } else {
                self::showHelp('Config file ' . $config_file . ' not found.');
            }
        } elseif (file_exists($config_file = APP_PATH . 'config/' . ENVIRONMENT . '/swoole_queue.php')
            || file_exists($config_file = APP_PATH . 'config/swoole_queue.php')
        ) {
            self::parseConfig($config_file);
        }

        /**
         * command line opts always override config file
         */


        if (isset($opts['r'])) {
            self::$config['max_request'] = (int)$opts['r'];
        }

        /**
         * If we want to daemonize, fork here and exit
         */
        if (isset($opts['d'])) {
            self::$config['daemonize'] = 1;
        }

        if (isset($opts['u'])) {
            self::$config['user'] = $opts['u'];
        }

        if (isset($opts['P'])) {
            self::$config['pid_file'] = $opts['P'];
        }

        /**
         * Debug option to dump the config and exit
         */
        if (isset($opts['Z'])) {
            print_r(self::$config);
            exit();
        }
    }

    /**
     * Parses the config file
     *
     * @param string $file The config file. Just pass so we don't have
     *                              to keep it around in a var
     */
    private static function parseConfig($file)
    {
        echo 'Loading configuration from ', $file, PHP_EOL;

        if (substr($file, -4) == '.php') {
            $swoole_config = require $file;
        }

        if (isset($swoole_config[self::DEFAULT_CONFIG])) {
            self::$config = $swoole_config[self::DEFAULT_CONFIG];
            self::$config['functions'] = [];
        }

        unset($swoole_config[self::DEFAULT_CONFIG]);

        if (empty($swoole_config)) {
            self::showHelp("No queue configuration found in $file");
        }

        foreach ($swoole_config as $function => $data) {
            self::$config['functions'][$function] = $data;
        }
    }

    private static function setProperty()
    {
        //if (!empty(self::$config['auto_update'])) {
        //self::$check_code = true;
        //}

        if (isset(self::$config['max_request']) && (int)self::$config['max_request'] > 0) {
            self::$max_request = (int)self::$config['max_request'];
        }
    }

    public static function start()
    {
        if (!function_exists('swoole_set_process_name')) {
            self::showHelp('The function swoole_set_process_name was not found. Please ensure swoole extension are installed');
        }

        if (!function_exists('posix_kill')) {
            self::showHelp('The function posix_kill was not found. Please ensure POSIX functions are installed');
        }

        /**
         * Parse command line options. Loads the config file as well
         */
        self::getOpt();
        self::setProperty();

        self::evolve();
        self::registerSignalHandle();

        log_message('info', 'Started master with pid ' . self::$pid);

        /**
         * Load up the queues
         */
        self::loadQueues();

        /**
         * Validate queues in the helper process
         */
        //self::fork_assist();

        /**
         * Start the initial queues and set up a running environment
         */
        self::bootstrap();

        /**
         * 增加一个检测work工作时间的定时器
         */
        //\Swoole\Process::alarm(3 * 1000 * 1000);

        self::processLoop();

        /**
         * Kill the helper if it is running
         */
        /*if (isset($this->helper_pid)) {
            \Swoole\Process::kill($this->helper_pid, SIGKILL);
        }*/

        log_message('info', 'Master Exiting');
    }

    private static function evolve()
    {
        /**
         * want to daemonize
         */
        if (!empty(self::$config['daemonize'])) {
            \Swoole\Process::daemon(true, true);
        }

        self::$pid = posix_getpid();
        swoole_set_process_name(sprintf('php_%s_swoole_queue:master', APP_NAME));

        if (!empty(self::$config['user'])) {
            self::$user = self::$config['user'];

            $user = posix_getpwnam(self::$user);
            if (!$user || !isset($user['uid']) || !isset($user['gid'])) {
                self::showHelp("User ({self::$user}) not found.");
            }

            /**
             * 切换完 uid 和 gid 之后再开始打日志
             */
            @posix_setgid($user['gid']);
            @posix_setuid($user['uid']);

            if (posix_getgid() == $user['gid']) {
                log_message('info', 'Set group to ' . self::$user . '\'s group');
            } else {
                log_message('notice',
                    'Unable to set group to ' . self::$user . '\'s group (GID: ' . $user['gid'] . ')');
            }

            if (posix_getuid() == $user['uid']) {
                log_message('info', 'Set user to ' . self::$user);
            } else {
                log_message('notice', 'Unable to set user to ' . self::$user . ' (UID: ' . $user['uid'] . ')');
            }
        }

        if (!empty(self::$config['pid_file'])) {
            $pid_dir = dirname(self::$config['pid_file']);
            if (!is_dir($pid_dir) && !mkdir_recursive($pid_dir, 0777)) {
                self::showHelp('Unable to make dir ' . $pid_dir);
            }

            $fp = @fopen(self::$config['pid_file'], 'w');
            if ($fp) {
                fwrite($fp, self::$pid);
                fclose($fp);
            } else {
                self::showHelp('Unable to write PID to ' . self::$config['pid_file']);
            }
            self::$pid_file = self::$config['pid_file'];

            /**
             * Ensure new uid can read/write pid and log files
             */
            if (!@chmod(self::$pid_file, 0666)) {
                log_message('notice', 'Unable to chmod PID file to 666');
            }
        }
    }

    /**
     * Helper function to load and filter worker files
     * return @void
     */
    private static function loadQueues()
    {
        self::$functions = [];


        foreach (self::$config['functions'] as $queue => $config) {
            if (isset($config['callback'])) {
                self::$functions[$queue]['mq_consumer'] = $config['mq_consumer'] ?? $queue;

                self::$functions[$queue]['process_num'] = max(intval($config['process_num']), 1);

                self::$functions[$queue]['callback'] = $config['callback'];
            }
        }

        self::$config = [];

        if (empty(self::$functions)) {
            log_message('info', 'No queues found');
            exit();
        }
    }

    /*private static function forkAssist()
    {
        $process = new \Swoole\Process(
            function (\Swoole\Process $process)
            {
                self::$is_parent = false;
                self::$parent_pid = self::$pid;
                self::$pid = posix_getpid();
                self::registerSignalHandle();
                $process->name(sprintf('php_%s_swoole_queue:assist(%d)', APP_NAME, self::$parent_pid));

                $process->exit(!self::validate_lib_queues());
            }, false, false
        );

        $pid = $process->start();

        if ($pid) {
            log_message('info', 'Assist forked with pid ' . $pid);

            self::$assist_pid = $pid;
            self::$wait_assist = true;

            while (self::$wait_assist && !self::$stop_work) {
                sleep(10);
            }
        } else {
            log_message('alert', 'Assist forked failed: ' . swoole_strerror(swoole_errno()) . '(' . swoole_errno() . ')');
            self::$stop_work = true;
        }

        if (self::$stop_work) {
            exit();
        }
    }*/

    /**
     * Bootstrap a set of queues and any vars that need to be set
     */
    private static function bootstrap()
    {
        /**
         * Next we loop the queues and ensure we have enough running
         * for each worker
         */
        foreach (self::$functions as $queue => $config) {

            $count = 0;

            while ($count < $config['process_num']) {
                self::startQueue($queue);
                $count++;
                /**
                 * Don't start queues too fast
                 */
                usleep(50000);
            }
        }
    }

    private static function startQueue($queue, $delay_second = 0)
    {
        do {
            $process = new \Swoole\Process(
                function (\Swoole\Process $process) use ($queue, $delay_second) {
                    if ($delay_second) {
                        sleep($delay_second);
                    }
                    self::$is_parent = false;
                    self::$parent_pid = self::$pid;
                    self::$pid = posix_getpid();
                    self::$children = [];
                    self::registerSignalHandle();

                    $process->name(sprintf('php_%s_swoole_queue:worker(%d)[%s]', APP_NAME, self::$parent_pid, $queue));

                    self::startLibQueue($queue);

                    log_message('info', 'Child exiting');
                }, false, false
            );

            $pid = $process->start();

            // parent
            if ($pid) {
                log_message('info', 'Started child ' . $pid . ' (' . $queue . ')');
                self::$children[$pid] = [
                    'queue' => $queue,
                    'start_time' => time() + $delay_second,
                ];
            } else {
                log_message('alert',
                    'Started child failed: ' . swoole_strerror(swoole_errno()) . '(' . swoole_errno() . ')');
                sleep(2);
            }
        } while (!$pid);
    }

    public static function checkParentProcess()
    {
        if (!\Swoole\Process::kill(self::$parent_pid, 0)) {
            self::$stop_work = true;

            log_message('info', 'Master process exited, I [' . self::$pid . '] also quit');
        }
    }

    /**
     * Function for logging the status of children. This simply logs the status
     * of the process. Wrapper classes can make use of this to do logging as
     * appropriate for individual environments.
     *
     * @param int $pid PID of the child process
     * @param string $queue Queue name
     * @param string $status Status of the child process.
     *                            One of killed, exited or non-zero integer
     *
     * @return void
     */
    private static function childStatusMonitor($pid, $queue, $status)
    {
        switch ($status) {
            case 'killed':
                $message = "Child $pid has been running too long. Forcibly killing process. ($queue)";
                break;
            case 'exited':
                $message = "Child $pid exited cleanly. ($queue)";
                break;
            default:
                $message = "Child $pid died unexpectedly with exit code $status. ($queue)";
                break;
        }
        log_message('info', $message);
    }

    /**
     * Shows the scripts help info with optional error message
     */
    private static function showHelp($msg = '')
    {
        if ($msg) {
            echo "ERROR:\n";
            echo '  ' . wordwrap($msg, 72, "\n  ") . "\n\n";
        }
        echo "Swoole http manager script\n\n";
        echo "USAGE:\n";
        echo "  # " . basename(__FILE__) . " -h | -c CONFIG [-l LOG_FILE] [-d] [-v LOG_LEVEL] [-a] [-P PID_FILE]\n\n";
        echo "OPTIONS:\n";
        echo "  -c CONFIG      加载的配置文件\n";
        echo "  -d             守护模式\n";
        echo "  -H             显示帮助\n";
        echo "  -P PID_FILE    进程PID文件存储位置\n";
        echo "  -u USERNAME    以某位用户的身份执行\n";
        echo "  -r NUMBER      每个work最大执行请求数，超过此数量会重启\n";
        echo "  -Z             打印配置文件并退出\n";
        echo "\n";
        exit();
    }

    private static function startLibQueue($queue)
    {
        $consumer_name = self::$functions[$queue]['mq_consumer'];

        mq_consumer($consumer_name)->setCallback(
            function ($msg) use ($queue) {
                $CI = new CI;
                log_message('debug', 'Creating a CI object');

                //pcntl_sigprocmask(SIG_BLOCK, [SIGTERM, SIGINT, SIGHUP]); //屏蔽信号
                return $CI->request(self::$functions[$queue]['callback'], $msg);
                //pcntl_sigprocmask(SIG_UNBLOCK, [SIGTERM, SIGINT, SIGHUP]); // 解除屏蔽
            }
        );

        while (!self::$stop_work) {
            mq_consumer($consumer_name)->consume();

            self::$job_execution_count++;

            if (self::$max_request && self::$job_execution_count >= self::$max_request) {
                log_message('info',
                    sprintf('Ran %s jobs which is over the maximum(%d), exiting', self::$job_execution_count,
                        self::$max_request));
                self::$stop_work = true;
            }

            self::checkParentProcess();
            usleep(5000);
        }
    }

    /**
     * Validates the PECL compatible worker files/functions
     */
    /*private static function validateLibQueues()
    {
        if (!class_exists($real_func) || !method_exists($real_func, 'run')) {
            log_message('alert', 'Function ' . $real_func . ' not found or not has run method');
            return false;
        }

        \Swoole\Process::kill(self::$parent_pid, SIGCONT);

        return true;
    }*/

    private static function processLoop()
    {
        /**
         * Main processing loop for the parent process
         */
        while (!self::$stop_work || count(self::$children)) {
            /**
             *  If any children have been running 200% of max run time, forcibly terminate them
             */
            /*if (!empty(self::$children)) {
                foreach (self::$children as $pid => $child) {
                    if (!empty($child['start_time']) && time() - $child['start_time'] > self::$max_run_time * 2) {
                        self::childStatusMonitor($pid, $child['queue'], 'killed');
                        \Swoole\Process::kill($pid, SIGKILL);
                    }
                }
            }*/

            sleep(300);
        }
    }

    private static function registerSignalHandle()
    {
        pcntl_async_signals(true); //开启异步信号处理

        //在父进程注册的信号会在子进程继承，所以需要清空，增加信号要成对出现
        if (self::$is_parent) {
            $stop_signo_handle = function ($signo) {
                log_message('info', 'Queue shutting down...');
                self::$stop_work = true;

                self::stopChildren();
            };

            pcntl_signal(SIGTERM, $stop_signo_handle);
            pcntl_signal(SIGINT, $stop_signo_handle);
            pcntl_signal(SIGHUP, $stop_signo_handle);

            /*pcntl_signal(
                SIGCONT,
                function ($signo)
                {
                    self::$wait_assist = false;
                }
            );*/

            pcntl_signal(
                SIGUSR2,
                function ($signo) {
                    log_message('info', 'Restarting children');
                    self::stopChildren();
                }
            );

            pcntl_signal(
                SIGCHLD,
                function ($signo) {
                    /**
                     * Check for exited children
                     */
                    while ($exited = \Swoole\Process::wait(false)) {
                        /**
                         * We run other children, make sure this is a worker
                         */
                        /*if ($exited['pid'] == self::$assist_pid) {
                            self::$assist_pid = null;
                            log_message('info', 'Assist child exited with code ' . $exited['code']);
                            if (($exited['code'] != 0 || self::$wait_assist == true) && !self::$stop_work) {
                                log_message('alert', 'Assist child has unexpectedly exited or been killed.');
                                self::$stop_work = true;
                            }
                        } else*/
                        if (isset(self::$children[$exited['pid']])) {
                            /**
                             * If they have exited, remove them from the children array
                             * If we are not stopping work, start another in its place
                             */
                            $queue = self::$children[$exited['pid']]['queue'];
                            /**
                             * 计算运行时间
                             */
                            $run_time = max(0, time() - self::$children[$exited['pid']]['start_time']);
                            unset(self::$children[$exited['pid']]);
                            $delay_second = 0;

                            if ($exited['code'] === 0) {
                                $exit_status = 'exited';
                            } else {
                                $exit_status = $exited['code'];

                                /**
                                 * 统计异常退出
                                 */
                                if ($run_time < 10) {
                                    log_message('alert',
                                        sprintf('%s queue start be aborted in %d sec', APP_NAME, $run_time));

                                    $delay_second = 15;
                                }
                            }
                            self::childStatusMonitor($exited['pid'], $queue, $exit_status);

                            if (!self::$stop_work) {
                                self::startQueue($queue, $delay_second);
                            }
                        }
                    }
                }
            );
        } else {
            pcntl_signal(SIGTERM, SIG_DFL);
            pcntl_signal(SIGINT, SIG_DFL);
            pcntl_signal(SIGHUP, SIG_DFL);
            //pcntl_signal(SIGCONT, SIG_DFL);
            pcntl_signal(SIGUSR2, SIG_IGN);
            pcntl_signal(SIGCHLD, SIG_IGN);
        }
    }

    private static function stopChildren($signal = SIGTERM)
    {
        /*if (isset(self::$assist_pid)) {
            \Swoole\Process::kill(self::$assist_pid, $signal);
        }*/

        foreach (self::$children as $pid => $child) {
            log_message('info', 'Stopping child ' . $child['queue'] . '(' . $pid . ')');
            \Swoole\Process::kill($pid, $signal);
        }
    }

    /**
     * Handles anything we need to do when we are shutting down
     */
    public function __destruct()
    {
        if (self::$is_parent) {
            if (!empty(self::$pid_file) && file_exists(self::$pid_file)) {
                if (!unlink(self::$pid_file)) {
                    log_message('error', 'Could not delete PID file');
                }
            }
        }
    }
}
