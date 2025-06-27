<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\TcpService;
use App\Manager\ConnectionManager;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use Hyperf\Contract\OnReceiveInterface;
use Swoole\Coroutine\Server\Connection;

class TcpServerController implements OnReceiveInterface
{
    #[Inject]
    protected TcpService $service;

    #[Inject]
    protected ConnectionManager $connection;

    #[Inject]
    protected LoggerInterface $logger;

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
        $this->logger->info("get tcp message: [{$fd}] {$data}");
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
        $this->logger->info($fd . ' closed');
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
        $this->logger->info('New Connection: ' . $fd);
        $this->connection->setConnection($fd, $conn);
    }
}
