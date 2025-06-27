<?php

declare(strict_types=1);

namespace App\Service;

use App\Manager\ChannelManager;
use App\Manager\GatewayManager;
use App\Manager\ConnectionManager;
use App\Util\Utils;
use App\Exception\GatewayOfflineException;
use App\Exception\InvalidRequestException;
use App\Exception\InvalidBackMessageException;
use Hyperf\Server\Exception\InvalidArgumentException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Engine\Coroutine;
use Hyperf\Server\Exception\ServerException;
use Psr\Log\LoggerInterface;

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

    #[Inject]
    protected LoggerInterface $logger;

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
        $this->logger->info('get http message: ' . json_encode($data));

        $missionId = $this->getMissionId();

        try {
            $this->channel->initAsync($missionId);

            // 校验
            if (!isset($data['machineId']) || !isset($data['gatewayId'])) {
                throw new InvalidRequestException('create mission error');
            }

            // 同一个电表同一时间的操作只能有一个
            if (!$this->channel->acquireMissionSlot($data['gatewayId'], $data['machineId'], $missionId, self::TIME_OUT)) {
                throw new InvalidRequestException('mission already exists: ' . $data['gatewayId'] . ' ' . $data['machineId']);
            }

            if (!$this->channel->check($missionId)) {
                throw new ServerException('mission not exists: ' . $missionId);
            }

            // 把数据发给tcp server
            $this->handleHttp($data);

            // 接收tcp server的返回
            $message = $this->channel->popMessage(Coroutine::id(), self::TIME_OUT);
            $this->logger->info("chanel message : {$message}");
            if ($message === false) {
                throw new ServerException('mission time out');
            }

            try {
                $jsonData = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
                if (!isset($jsonData['status']) || !isset($jsonData['data'])) {
                    throw new InvalidBackMessageException('back message error');
                }

                return $jsonData;
            } catch (\Throwable $e) {
                $this->logger->error('back error: ' . $e->getMessage());
                throw $e;
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return ['status' => 0, 'data' => $e->getMessage()];
        } finally {
            // 无论成功、失败还是超时，最终都必须释放任务槽，让其他等待的协程可以继续
            if (!empty($data['gatewayId']) && !empty($data['machineId'])) {
                $this->channel->releaseMissionSlot($data['gatewayId'], $data['machineId'], $missionId);
            }
        }
    }

    /**
     * 
     * @param array $data 
     * @return void 
     */
    public function handleHttp(array $data): void
    {
        if (!isset($data['gatewayId'])) {
            throw new InvalidArgumentException('create mission error');
        }

        $gateway = $this->gateway->findGatewayByName($data['gatewayId']);
        if (!$gateway) {
            throw new GatewayOfflineException('gateway not on line: ' . $data['gatewayId']);
        }

        $gatewayConnection = $this->connection->getConnection($gateway);
        if (!$gatewayConnection) {
            throw new GatewayOfflineException('gateway not on line: ' . $gateway);
        }

        $result = $gatewayConnection->send(Utils::decodeHexString($data['data']));
        $this->logger->info('send message to gateway ' . $gateway . ': ' . $data['data'] . ', result: ' . ($result === false ? 'failed' : 'success'));
        if ($result === false) {
            // 发送失败，立即返回错误
            throw new ServerException('failed to send command to gateway: ' . $data['gatewayId']);
        }
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
