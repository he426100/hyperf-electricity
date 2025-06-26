<?php

declare(strict_types=1);

namespace App\Service;

use App\Manager\ChannelManager;
use App\Manager\GatewayManager;
use App\Manager\ConnectionManager;
use App\Util\Utils;
use App\Exception\ControllerOfflineException;
use App\Exception\InvalidRequestException;
use App\Exception\InvalidBackMessageException;
use Hyperf\Server\Exception\InvalidArgumentException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Engine\Coroutine;
use function FriendsOfHyperf\Helpers\logs;

/**
 * @package App\Service
 */
class HttpService
{
    #[Inject]
    protected ChannelManager $channel;

    #[Inject]
    protected GatewayManager $gateway;

    #[Inject]
    protected ConnectionManager $connection;

    public const TIME_OUT = 5;

    /**
     * 
     * @param array $data 
     * @return array 返回包含status和data的关联数组
     * - status
     * - data
     */
    public function handle(array $data): array
    {
        logs()->info('get http message: ' . json_encode($data));

        $missionId = $this->getMissionId();

        try {
            $this->channel->initAsync($missionId);

            // 校验
            if (!isset($data['machineId']) || !isset($data['gatewayId'])) {
                return ['status' => 0, 'data' => 'create mission error'];
            }

            // 同一个电表同一时间的操作只能有一个
            if (!$this->channel->acquireMissionSlot($data['gatewayId'], $data['machineId'], $missionId, self::TIME_OUT)) {
                logs()->warning('mission already exists: ' . $data['gatewayId'] . ' ' . $data['machineId']);
                return ['status' => 0, 'data' => 'mission already exists'];
            }

            if (!$this->channel->check($missionId)) {
                logs()->warning('mission not exists: ' . $missionId);
                return ['status' => 0, 'data' => 'send message error'];
            }

            // 向tcp server发送请求
            $message = $this->handleHttp($data);
            if (!is_null($message)) {
                return $message;
            }

            // 等待tcp server的返回
            $message = $this->channel->popMessage(Coroutine::id(), self::TIME_OUT);
            logs()->info("chanel message : {$message}");
            if ($message === false) {
                return ['status' => 0, 'data' => 'mission time out'];
            }

            try {
                $jsonData = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
                if (!isset($jsonData['status']) || !isset($jsonData['data'])) {
                    throw new InvalidBackMessageException('back message error');
                }

                return $jsonData;
            } catch (\Throwable $e) {
                logs()->error('back error: ' . $e->getMessage());
            }
        } finally {
            // 无论成功、失败还是超时，最终都必须释放任务槽，让其他等待的协程可以继续
            if (!empty($data['gatewayId']) && !empty($data['machineId'])) {
                $this->channel->releaseMissionSlot($data['gatewayId'], $data['machineId'], $missionId);
            }
        }

        return ['status' => 0, 'data' => 'unkonw message: ' . $message];
    }

    /**
     * 
     * @param array $data 
     * @return ?array 
     */
    public function handleHttp(array $data): ?array
    {
        if (!isset($data['gatewayId'])) {
            throw new InvalidArgumentException('create mission error');
        }

        $gateway = $this->gateway->findGatewayByName($data['gatewayId']);
        if (!$gateway) {
            logs()->warning('gateway not on line: ' . $data['gatewayId']);
            return ['status' => 0, 'data' => 'gateway not on line'];
        }

        $gatewayConnection = $this->connection->getConnection($gateway);
        if (!$gatewayConnection) {
            logs()->warning('gateway not on line: ' . $gateway);
            return ['status' => 0, 'data' => 'gateway not on line'];
        }

        $result = $gatewayConnection->send(Utils::decodeHexString($data['data']));
        logs()->info('send message to gateway ' . $gateway . ': ' . $data['data'] . ', result: ' . ($result === false ? 'failed' : 'success'));
        if ($result === false) {
            // 发送失败，立即返回错误
            return ['status' => 0, 'data' => 'failed to send command to gateway'];
        }
        return null;
    }

    /**
     * 
     * @return int 
     */
    public function getMissionId(): int
    {
        return Coroutine::id();
    }
}
