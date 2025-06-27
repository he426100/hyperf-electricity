<?php

declare(strict_types=1);

namespace App\Service;

use App\Manager\ChannelManager;
use App\Manager\GatewayManager;
use App\Manager\ConnectionManager;
use App\Util\Utils;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Contract\StdoutLoggerInterface;
use Swoole\Coroutine\Server\Connection;
use function Hyperf\Support\env;

/**
 * @package App\Service
 */
class TcpService
{
    #[Inject]
    protected ChannelManager $channel;

    #[Inject]
    protected GatewayManager $gateway;

    #[Inject]
    protected ConnectionManager $connection;

    #[Inject]
    protected StdoutLoggerInterface $logger;

    private const MESSAGE_PING = '{"type":"ping","data":1}';
    private const MESSAGE_PONG = '{"type":"ping","data":1}';

    private const MESSAGE_TYPE_LOGIN = 'login';
    private const LOGIN_RESP_DATA = '{"type":"login","data":1}';

    /**
     * @param Connection $conn
     * @param int $fd 
     * @param string $message json|bin
     * @return void 
     */
    public function handle(Connection $conn, int $fd, string $message): void
    {
        $name = "";
        if ($gatewayName = $this->gateway->findGatewayByFd($fd)) {
            $name = "machine " . $gatewayName;
            $this->logger->info($fd . " is Gateway User: " . $name);
        }
        $this->logger->info("get message $name : $message");

        // 检测 PING
        if ($message == self::MESSAGE_PING) {
            $conn->send(self::MESSAGE_PONG);
            return;
        }

        try {
            if (Utils::isJson($message)) {
                $data = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
                $this->handleLogin($conn, $fd, $data);
                return;
            }

            if ($this->gateway->findGatewayByFd($fd)) {
                $this->handleGateway($conn, $fd, $message);
            }
        } catch (\Throwable $e) {
            $this->logger->error('handle error: ' . $e->getMessage() . ', message: ' . (isset($data) ? json_encode($data) : bin2hex($message)));
        }
    }

    /**
     * 
     * @param Connection $conn
     * @param int $fd 
     * @param string $data 
     * @return void 
     */
    public function handleGateway(Connection $conn, int $fd, string $data): void
    {
        $hexStr = bin2hex($data);
        $hexStr = strtoupper($hexStr);
        $this->logger->info("hexStr : {$hexStr}");

        // 拆解
        if (strlen($hexStr) <= 4) {
            throw new \Exception('error gateway message: ' . $hexStr);
        }

        // 约定返回的第一个字节为电表ID
        $machineId = substr($hexStr, 0, 2);
        $gatewayName = $this->gateway->findGatewayByFd($fd);
        if (!$gatewayName) {
            throw new \Exception('gateway error: ' . $fd);
        }
        $hexStr = Utils::formatHexWithSpaces($hexStr);
        $result = $this->channel->pushMessage(json_encode([
            'gatewayId' => $gatewayName,
            'machineId' => $machineId,
            'result' => [
                'status' => 1,
                'data' => $hexStr
            ]
        ]));
        $this->logger->info('send message to http: ' . $hexStr . ', result: ' . ($result ? 'success' : 'failed'));
    }

    /**
     * 
     * @param int $fd 
     * @param array $data 
     * @return void 
     */
    public function handleLogin(Connection $conn, int $fd, array $data): void
    {
        if (($data['type'] ?? '') != self::MESSAGE_TYPE_LOGIN || ($data['key'] ?? '') != env('GATEWAY_KEY') || empty($data['name'] ?? '')) {
            throw new \Exception('login error: ' . json_encode($data));
        }
        $this->gateway->login($fd, $data['name']);

        $sendMessage = self::LOGIN_RESP_DATA;
        $conn->send($sendMessage);
        $this->logger->info("login success : $sendMessage");
    }

    /**
     * 
     * @param Connection $conn
     * @param int $fd 
     * @return void 
     */
    public function handleClose(Connection $conn, int $fd)
    {
        $this->gateway->logout($fd);
    }
}
