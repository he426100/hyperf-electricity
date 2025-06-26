<?php

declare(strict_types=1);

namespace App\Manager;

use Hyperf\Engine\Channel;
use Hyperf\Engine\Coroutine;
use Hyperf\Server\Exception\InvalidArgumentException;
use Swoole\ArrayObject;
use function FriendsOfHyperf\Helpers\logs;

/**
 * http会话管理器
 * @package App\Manager
 */
class ChannelManager
{
    public const CHANNEL_NAME = 'http_recv';

    // 设置 Channel 最大存活时间（秒）
    private const CHANNEL_TTL = 15;

    /**
     * @var array<string, array{channel: Channel, created: int, missionId: int|null}>
     * 一个电表只允许存在一个会话
     */
    private array $missionChannels = [];

    /**
     * 
     * @param int $id 
     * @return void 
     */
    public function initAsync(int $id): void
    {
        $channel = new Channel(1);
        Coroutine::getContextFor($id)->offsetSet(self::CHANNEL_NAME, $channel);
    }

    /**
     * 为指定 machine 获取任务执行权（原子操作）.
     * @param string $gatewayId 
     * @param string $machineId 
     * @param int $id 
     * @param float $timeout 获取锁的超时时间
     * @return bool 
     */
    public function acquireMissionSlot(string $gatewayId, string $machineId, int $id, float $timeout = 1): bool
    {
        $this->cleanExpiredChannels();

        $key = $gatewayId . '_' . $machineId;
        if (!isset($this->missionChannels[$key])) {
            $this->missionChannels[$key] = [
                'channel' => new Channel(1),
                'created' => time(),
                'missionId' => null,
            ];
        }

        if ($this->missionChannels[$key]['channel']->push($id, $timeout)) {
            $this->missionChannels[$key]['missionId'] = $id;
            $this->missionChannels[$key]['created'] = time();
            return true;
        }
        return false;
    }

    /**
     * 释放任务槽，让其他等待的协程可以执行.
     * @param string $gatewayId 
     * @param string $machineId 
     * @param int $id 
     * @return void 
     */
    public function releaseMissionSlot(string $gatewayId, string $machineId, int $id): void
    {
        $key = $gatewayId . '_' . $machineId;

        if (isset($this->missionChannels[$key]) && $this->missionChannels[$key]['missionId'] === $id) {
            // 如果通道不为空，pop出数据，从而释放锁
            if (!$this->missionChannels[$key]['channel']->isEmpty()) {
                $this->missionChannels[$key]['channel']->pop(0.001); // 非阻塞pop
            }
            $this->missionChannels[$key]['missionId'] = null;
        }
    }

    /**
     * 
     * @param int $id 
     * @return bool 
     */
    public function check(int $id)
    {
        return Coroutine::getContextFor($id)->offsetExists(self::CHANNEL_NAME);
    }

    /**
     * 拉取来自tcp server的消息
     * @param int $id 
     * @param float $timeout 
     * @return mixed 
     */
    public function popMessage(int $id, float $timeout): mixed
    {
        /** @var Channel */
        $channel = Coroutine::getContextFor($id)->offsetGet(self::CHANNEL_NAME);
        return $channel->pop($timeout);
    }

    /**
     * 
     * @param int $id 
     * @return bool 
     */
    public function isPopTimeout(int $id): bool
    {
        /** @var Channel */
        $channel = Coroutine::getContextFor($id)->offsetGet(self::CHANNEL_NAME);
        return $channel->isTimeout();
    }

    /**
     * 推送消息到指定http session
     * @param string $message 
     * @return bool 
     */
    public function pushMessage(string $message): bool
    {
        try {
            $data = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
            if (!isset($data['gatewayId']) || !isset($data['machineId'])) {
                throw new InvalidArgumentException('消息格式不正确');
            }

            $key = $data['gatewayId'] . '_' . $data['machineId'];

            if (!isset($this->missionChannels[$key]) || $this->missionChannels[$key]['missionId'] === null) {
                throw new \Exception("Channel for {$key} not found");
            }

            $id = $this->missionChannels[$key]['missionId'];

            if (!Coroutine::exists($id)) {
                throw new \Exception('http session ' . $id . ' not exists');
            }

            /** @var ArrayObject $context */
            $context = Coroutine::getContextFor($id);
            if (!$context->offsetExists(self::CHANNEL_NAME)) {
                throw new \Exception($id . ' not http session or http not login');
            }

            /** @var Channel */
            $channel = $context->offsetGet(self::CHANNEL_NAME);
            return $channel->push(json_encode($data['result']), 1);
        } catch (\Throwable $e) {
            logs()->error('pushMessage.error: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * 清理过期channel
     * @return void
     */
    private function cleanExpiredChannels(): void
    {
        $now = time();
        foreach ($this->missionChannels as $key => $data) {
            // 只有当通道为空（即没有任务在执行）且超过TTL时，才清理
            if ($data['channel']->isEmpty() && $now - $data['created'] > self::CHANNEL_TTL) {
                $this->removeChannel($key);
            }
        }
    }

    /**
     * 移除指定 Channel
     * @param string $key
     * @return void
     */
    private function removeChannel(string $key): void
    {
        if (isset($this->missionChannels[$key])) {
            $this->missionChannels[$key]['channel']->close();
            unset($this->missionChannels[$key]);
            logs()->info("Channel {$key} has been cleaned up due to TTL.");
        }
    }
}
