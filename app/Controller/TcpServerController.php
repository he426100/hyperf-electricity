<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TcpService;
use App\Manager\ConnectionManager;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Contract\OnReceiveInterface;
use Swoole\Coroutine\Server\Connection;

use function FriendsOfHyperf\Helpers\logs;

class TcpServerController implements OnReceiveInterface
{
    #[Inject]
    protected TcpService $service;

    #[Inject]
    protected ConnectionManager $connection;

    /**
     * 
     * @param Connection $conn 
     * @param int $fd 
     * @param int $reactorId 
     * @param string $data 
     * @return void 
     */
    public function onReceive($conn, int $fd, int $reactorId, string $data): void
    {
        logs()->info("get tcp message: [{$fd}] {$data}");
        $this->service->handle($conn, $fd, $data);
    }

    /**
     * 
     * @param Connection $conn
     * @param int $fd 
     * @return void 
     */
    public function onClose($conn, int $fd): void
    {
        logs()->info($fd . ' closed');
        $this->connection->setConnection($fd, null);
        $this->service->handleClose($conn, $fd);
    }

    /**
     * 
     * @param Connection $conn
     * @param int $fd 
     * @return void 
     */
    public function onConnect($conn, int $fd): void
    {
        logs()->info('New Connection: ' . $fd);
        $this->connection->setConnection($fd, $conn);
    }
}
