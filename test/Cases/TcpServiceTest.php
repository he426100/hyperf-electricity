<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Manager\ChannelManager;
use App\Manager\GatewayManager;
use App\Manager\ConnectionManager;
use App\Service\TcpService;
use App\Util\Utils;
use Hyperf\Testing\TestCase;
use Hyperf\Contract\StdoutLoggerInterface;
use Swoole\Coroutine\Server\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(TcpService::class)]
class TcpServiceTest extends TestCase
{
    public function testLogin()
    {
        $data = '{"type":"login","key":"","name":"modbus"}';
        $sendMessage = '{"type":"login","data":1}';

        $logger = $this->mock(StdoutLoggerInterface::class);
        $logger->shouldReceive('info')->with('1 is Gateway User: machine modbus')->andReturn(null);
        $logger->shouldReceive('info')->with('get message machine modbus : ' . $data)->andReturn(null);
        $logger->shouldReceive('info')->with('login success : ' . $sendMessage)->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByFd')->with(1)->andReturn('modbus');
        $gateway->shouldReceive('login')->with(1, 'modbus')->andReturn(null);

        $conn = $this->mock(Connection::class);
        $conn->shouldReceive('send')->with($sendMessage)->andReturn(null);

        $service = $this->container->get(TcpService::class);
        $service->handle($conn, 1, $data);

        $this->assertTrue(true);
    }

    public function testPing()
    {
        $data = '{"type":"ping","data":1}';
        $sendMessage = '{"type":"ping","data":1}';

        $logger = $this->mock(StdoutLoggerInterface::class);
        $logger->shouldReceive('info')->with('1 is Gateway User: machine modbus')->andReturn(null);
        $logger->shouldReceive('info')->with('get message machine modbus : ' . $data)->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByFd')->with(1)->andReturn('modbus');
        $gateway->shouldReceive('login')->with(1, 'modbus')->andReturn(null);

        $conn = $this->mock(Connection::class);
        $conn->shouldReceive('send')->with($sendMessage)->andReturn(null);

        $service = $this->container->get(TcpService::class);
        $service->handle($conn, 1, $data);

        $this->assertTrue(true);
    }

    public function testHandle()
    {
        $data = hex2bin('01000000');
        $sendMessage = json_encode([
            'gatewayId' => 'modbus',
            'machineId' => '01',
            'result' => [
                'status' => 1,
                'data' => '01 00 00 00'
            ]
        ]);
        
        $logger = $this->mock(StdoutLoggerInterface::class);
        $logger->shouldReceive('info')->with('1 is Gateway User: machine modbus')->andReturn(null);
        $logger->shouldReceive('info')->with('get message machine modbus : ' . $data)->andReturn(null);
        $logger->shouldReceive('info')->with('hexStr : 01000000')->andReturn(null);
        $logger->shouldReceive('info')->with('send message to http: 01 00 00 00, result: success')->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByFd')->with(1)->times(3)->andReturn('modbus');

        $conn = $this->mock(Connection::class);

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldReceive('pushMessage')->with($sendMessage)->andReturn(true);

        $service = $this->container->get(TcpService::class);
        $service->handle($conn, 1, $data);

        $this->assertTrue(true);
    }

    public function testHandleWithoutLogin()
    {
        $data = hex2bin('01000000');
        
        $logger = $this->mock(StdoutLoggerInterface::class);
        $logger->shouldReceive('info')->with('get message  : ' . $data)->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByFd')->with(1)->times(2)->andReturn(null);

        $conn = $this->mock(Connection::class);
        $conn->shouldNotReceive('send');

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldNotReceive('pushMessage');

        $service = $this->container->get(TcpService::class);
        $service->handle($conn, 1, $data);

        $this->assertTrue(true);
    }
}
