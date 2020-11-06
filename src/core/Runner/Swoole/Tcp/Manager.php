<?php

namespace CI\core\Runner\Swoole\Tcp;

class Manager
{
    /**
     * @var CI
     */
    private $CI;

    public static function start()
    {
        $serv = new \Swoole\Server('0.0.0.0', 0);

        $serv->set(
            [
                'worker_num' => 1,    //worker process num
                'task_worker_num' => 10,
                'backlog' => 128,   //listen backlog
                'max_request' => 5000,
                'task_max_request' => 5000,
                'dispatch_mode' => 2,
                'daemonize' => true, //是否作为守护进程
            ]
        );

        $serv->on(
            'WorkerStart',
            function (\Swoole\Server $serv, $worker_id) {
                $this->CI = new CI();

                $this->CI->onWorkerStart($serv, $worker_id);
            }
        );

        /*$serv->on(
            'Timer',
            function (\Swoole\Server $serv, $interval) {
                $this->CI->onTimer($serv, $interval);
            }
        );*/

        $serv->on(
            'Task',
            function (\Swoole\Server $serv, $task_id, $from_id, $data) {
                return $this->CI->OnTask($serv, $task_id, $from_id, $data);
            }
        );

        $serv->on(
            'Finish',
            function (\Swoole\Server $serv, $task_id, $data) {
                $this->CI->OnFinish($serv, $task_id, $data);
            }
        );

        $serv->on(
            'Connect',
            function ($serv, $fd) {
                echo "Client:Connect.\n";
            }
        );

        $serv->on(
            'Receive',
            function ($serv, $fd, $from_id, $data) {
                $res = $this->CI->request($data);
                $serv->send($fd, $res);
            }
        );

        $serv->on(
            'Close',
            function ($serv, $fd) {
                echo "Client: Close.\n";
            }
        );
        $serv->start();
    }

}