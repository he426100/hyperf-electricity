<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use Hyperf\Server\Event;
use Hyperf\Server\Server;
use Swoole\Constant;

return [
    'type' => Hyperf\Server\CoroutineServer::class,
    'servers' => [
        [
            'name' => 'http',
            'type' => Server::SERVER_HTTP,
            'host' => '0.0.0.0',
            'port' => 9501,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_REQUEST => [Hyperf\HttpServer\Server::class, 'onRequest'],
            ],
        ],
        [
            'name' => 'tcp',
            'type' => Server::SERVER_BASE,
            'host' => '0.0.0.0',
            'port' => 9504,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                Event::ON_CONNECT => [App\Controller\TcpServerController::class, 'onConnect'],
                Event::ON_RECEIVE => [App\Controller\TcpServerController::class, 'onReceive'],
                Event::ON_CLOSE   => [App\Controller\TcpServerController::class, 'onClose'],
            ],
            'settings' => [
                'open_tcp_keepalive' => true,
                'tcp_keepidle' => 10, //10s没有数据传输就进行检测
                'tcp_keepinterval' => 1, //1s探测一次
                'tcp_keepcount' => 10, //探测的次数，超过10次后还没回包close此连接
                'heartbeat_idle_time'      => 60, // 表示一个连接如果600秒内未向服务器发送任何数据，此连接将被强制关闭
                'heartbeat_check_interval' => 10,  // 表示每10秒遍历一次
            ],
        ],
    ],
    'settings' => [
        Constant::OPTION_ENABLE_COROUTINE => true,
        Constant::OPTION_WORKER_NUM => 1,
        Constant::OPTION_PID_FILE => BASE_PATH . '/runtime/hyperf.pid',
        Constant::OPTION_OPEN_TCP_NODELAY => true,
        Constant::OPTION_MAX_COROUTINE => 100000,
        Constant::OPTION_OPEN_HTTP2_PROTOCOL => true,
        Constant::OPTION_MAX_REQUEST => 100000,
        Constant::OPTION_SOCKET_BUFFER_SIZE => 2 * 1024 * 1024,
        Constant::OPTION_BUFFER_OUTPUT_SIZE => 2 * 1024 * 1024,
    ],
    'callbacks' => [
        Event::ON_WORKER_START => [Hyperf\Framework\Bootstrap\WorkerStartCallback::class, 'onWorkerStart'],
        Event::ON_PIPE_MESSAGE => [Hyperf\Framework\Bootstrap\PipeMessageCallback::class, 'onPipeMessage'],
        Event::ON_WORKER_EXIT => [Hyperf\Framework\Bootstrap\WorkerExitCallback::class, 'onWorkerExit'],
    ],
];
