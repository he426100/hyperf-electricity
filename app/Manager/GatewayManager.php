<?php

declare(strict_types=1);

namespace App\Manager;

/**
 * GatewayManager TCP设备管理器
 * @package App\Manager
 */
class GatewayManager
{
    /** [id => fd] */
    protected array $gateways = [];

    /**
     * 
     * @param int $fd 
     * @param string $id 
     * @return void 
     */
    public function login(int $fd, string $id): void
    {
        $this->gateways[$id] = $fd;
    }

    /**
     * 
     * @param int $fd 
     * @return null|string 
     */
    public function findGatewayByFd(int $fd): ?string
    {
        foreach ($this->gateways as $key => $item) {
            if ($item == $fd) return $key;
        }
        return null;
    }

    /**
     * 
     * @param string $name 
     * @return null|integer
     */
    public function findGatewayByName(string $name): ?int
    {
        return $this->gateways[$name] ?? null;
    }

    /**
     * 
     * @return array 
     */
    public function getGatewayNameList(): array
    {
        return array_keys($this->gateways);
    }

    /**
     * 
     * @param integer $fd 
     * @return void 
     */
    public function logout(int $fd)
    {
        $gatewayName = $this->findGatewayByFd($fd);
        if ($gatewayName) {
            unset($this->gateways[$gatewayName]);
        }
    }
}
