<?php

declare(strict_types=1);

namespace App\Manager;

use Swoole\Coroutine\Server\Connection;

/**
 * tcp会话管理器
 * @package App\Manager
 */
class ConnectionManager
{
    /** [fd => conn] */
    protected array $connections = [];

    /**
     * 
     * @param int $fd 
     * @return null|Connection 
     */
    public function getConnection(int $fd): ?Connection
    {
        return $this->connections[$fd]?? null;
    }

    /**
     * 
     * @return array Connection[]
     */
    public function getConnections(): array
    {
        return array_values($this->connections);
    }

    /**
     * 
     * @param int $fd 
     * @param null|Connection $conn 
     * @return void 
     */
    public function setConnection(int $fd, ?Connection $conn = null): void
    {
        if ($conn === null) {
            unset($this->connections[$fd]);
        } else {
            $this->connections[$fd] = $conn;
        }
    }
}
