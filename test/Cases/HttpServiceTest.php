<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Manager\ChannelManager;
use App\Manager\GatewayManager;
use App\Manager\ConnectionManager;
use App\Service\HttpService;
use App\Util\Utils;
use Hyperf\Testing\TestCase;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Server\Connection;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @internal
 */
#[CoversClass(HttpService::class)]
class HttpServiceTest extends TestCase
{
    public function testHandleWithoutGatewayId()
    {
        $data = '01 00 00 00';
        $post = ['machineId' => '01', 'data' => $data];
        $errMsg = 'create mission error';

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('error')->with($errMsg)->andReturn(null);

        $service = $this->container->get(HttpService::class);
        $result = $service->handle($post);

        $this->assertEquals(0, $result['status']);
        $this->assertEquals($errMsg, $result['data']);
    }

    public function testHandleWithoutMachineId()
    {
        $data = '01 00 00 00';
        $post = ['gatewayId' => 'modbus', 'data' => $data];
        $errMsg = 'create mission error';

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('error')->with($errMsg)->andReturn(null);

        $service = $this->container->get(HttpService::class);
        $result = $service->handle($post);

        $this->assertEquals(0, $result['status']);
        $this->assertEquals($errMsg, $result['data']);
    }

    public function testHandleMissionAlreadyExists()
    {
        $data = '01 00 00 00';
        $post = ['gatewayId' => 'modbus', 'machineId' => '01', 'data' => $data];
        $errMsg = 'mission already exists: modbus 01';
     
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('error')->with($errMsg)->andReturn(null);

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldReceive('initAsync')->andReturn(null);
        $channel->shouldReceive('acquireMissionSlot')->andReturn(false);
        $channel->shouldReceive('releaseMissionSlot')->andReturn(null);

        $service = $this->container->get(HttpService::class);
        $result = $service->handle($post);

        $this->assertEquals(0, $result['status']);
        $this->assertEquals($errMsg, $result['data']);
    }

    public function testHandleMissionNotExists()
    {
        $data = '01 00 00 00';
        $post = ['gatewayId' => 'modbus', 'machineId' => '01', 'data' => $data];
        $errMsg = 'mission not exists: 1';
     
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('error')->with($errMsg)->andReturn(null);

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldReceive('initAsync')->andReturn(null);
        $channel->shouldReceive('acquireMissionSlot')->andReturn(true);
        $channel->shouldReceive('check')->andReturn(false);
        $channel->shouldReceive('releaseMissionSlot')->andReturn(null);

        $service = $this->container->get(HttpService::class);
        $result = $service->handle($post);

        $this->assertEquals(0, $result['status']);
        $this->assertEquals($errMsg, $result['data']);
    }

    public function testHandle()
    {
        $data = '01 00 00 00';
        $post = ['gatewayId' => 'modbus', 'machineId' => '01', 'data' => $data];
        $result = [
            'status' => 1,
            'data' => [
                'status' => 1,
                'data' => $data,
            ],
        ];

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('info')->with('send message to gateway 1: ' . $data . ', result: success')->andReturn(null);
        $logger->shouldReceive('info')->with('chanel message : ' . json_encode($result))->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByName')->andReturn(1);

        $conn = $this->mock(Connection::class);
        $conn->shouldReceive('send')->with(Utils::decodeHexString($data))->andReturn(true);

        $connection = $this->mock(ConnectionManager::class);
        $connection->shouldReceive('getConnection')->andReturn($conn);

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldReceive('initAsync')->andReturn(null);
        $channel->shouldReceive('acquireMissionSlot')->andReturn(true);
        $channel->shouldReceive('check')->andReturn(true);
        $channel->shouldReceive('popMessage')->andReturn(json_encode($result));
        $channel->shouldReceive('releaseMissionSlot')->andReturn(null);

        $service = $this->container->get(HttpService::class);
        $result = $service->handle($post);

        $this->assertEquals(1, $result['status']);
        $this->assertIsArray($result['data']);
        $this->assertNotEmpty($result['data']);
        $this->assertEquals(1, $result['data']['status']);
        $this->assertEquals($data, $result['data']['data']);
    }

    public function testHandleWithInvalidgatewayId()
    {
        $data = '01 00 00 00';
        $post = ['gatewayId' => 'modbus', 'machineId' => '01', 'data' => $data];
        $errMsg = 'gateway not on line: modbus';

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('error')->with($errMsg)->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByName')->andReturn(null);

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldReceive('initAsync')->andReturn(null);
        $channel->shouldReceive('acquireMissionSlot')->andReturn(true);
        $channel->shouldReceive('check')->andReturn(true);
        $channel->shouldReceive('releaseMissionSlot')->andReturn(null);

        $service = $this->container->get(HttpService::class);

        $result = $service->handle($post);
        $this->assertEquals(0, $result['status']);
        $this->assertEquals($errMsg, $result['data']);
    }

    public function testHandlePopMessageReturnFalse()
    {
        $data = '01 00 00 00';
        $post = ['gatewayId' => 'modbus', 'machineId' => '01', 'data' => $data];
        $errMsg = 'mission time out';
     
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('info')->with('send message to gateway 1: ' . $data . ', result: success')->andReturn(null);
        $logger->shouldReceive('info')->with('chanel message : ')->andReturn(null);
        $logger->shouldReceive('error')->with($errMsg)->andReturn(null);

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldReceive('initAsync')->andReturn(null);
        $channel->shouldReceive('acquireMissionSlot')->andReturn(true);
        $channel->shouldReceive('check')->andReturn(true);
        $channel->shouldReceive('popMessage')->andReturn(false);
        $channel->shouldReceive('releaseMissionSlot')->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByName')->andReturn(1);

        $conn = $this->mock(Connection::class);
        $conn->shouldReceive('send')->with(Utils::decodeHexString($data))->andReturn(true);

        $connection = $this->mock(ConnectionManager::class);
        $connection->shouldReceive('getConnection')->andReturn($conn);

        $service = $this->container->get(HttpService::class);
        $result = $service->handle($post);

        $this->assertEquals(0, $result['status']);
        $this->assertEquals($errMsg, $result['data']);
    }

    public function testHandleSendFalse()
    {
        $data = '01 00 00 00';
        $post = ['gatewayId' => 'modbus', 'machineId' => '01', 'data' => $data];
        $errMsg = 'failed to send command to gateway: modbus';

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('info')->with('send message to gateway 1: ' . $data . ', result: failed')->andReturn(null);
        $logger->shouldReceive('info')->with('chanel message : ')->andReturn(null);
        $logger->shouldReceive('error')->with($errMsg)->andReturn(null);

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldReceive('initAsync')->andReturn(null);
        $channel->shouldReceive('acquireMissionSlot')->andReturn(true);
        $channel->shouldReceive('check')->andReturn(true);
        $channel->shouldReceive('releaseMissionSlot')->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByName')->andReturn(1);

        $conn = $this->mock(Connection::class);
        $conn->shouldReceive('send')->with(Utils::decodeHexString($data))->andReturn(false);

        $connection = $this->mock(ConnectionManager::class);
        $connection->shouldReceive('getConnection')->andReturn($conn);

        $service = $this->container->get(HttpService::class);
        $result = $service->handle($post);

        $this->assertEquals(0, $result['status']);
        $this->assertEquals($errMsg, $result['data']);
    }

    public function testHandleJsonError()
    {
        $data = '01 00 00 00';
        $post = ['gatewayId' => 'modbus', 'machineId' => '01', 'data' => $data];
        $result = '{{';

        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('info')->with('send message to gateway 1: ' . $data . ', result: success')->andReturn(null);
        $logger->shouldReceive('info')->with('chanel message : ' . $result)->andReturn(null);
        $logger->shouldReceive('error')->with('back error: Syntax error')->andReturn(null);
        $logger->shouldReceive('error')->with('Syntax error')->andReturn(null);

        $gateway = $this->mock(GatewayManager::class);
        $gateway->shouldReceive('findGatewayByName')->andReturn(1);

        $conn = $this->mock(Connection::class);
        $conn->shouldReceive('send')->with(Utils::decodeHexString($data))->andReturn(true);

        $connection = $this->mock(ConnectionManager::class);
        $connection->shouldReceive('getConnection')->andReturn($conn);

        $channel = $this->mock(ChannelManager::class);
        $channel->shouldReceive('initAsync')->andReturn(null);
        $channel->shouldReceive('acquireMissionSlot')->andReturn(true);
        $channel->shouldReceive('check')->andReturn(true);
        $channel->shouldReceive('popMessage')->andReturn($result);
        $channel->shouldReceive('releaseMissionSlot')->andReturn(null);

        $service = $this->container->get(HttpService::class);
        $result = $service->handle($post);

        $this->assertEquals(0, $result['status']);
        $this->assertEquals('Syntax error', $result['data']);
    }
}
