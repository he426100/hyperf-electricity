<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\Cases;

use Hyperf\Testing\TestCase;
use Psr\Log\LoggerInterface;
use Hyperf\Logger\LoggerFactory;

/**
 * @internal
 * @coversNothing
 */
class ExampleTest extends TestCase
{
    public function testExample()
    {
        $post = ['machineId' => '01', 'gatewayId' => 'modbus', 'data' => '01 00 00 00'];
        $logger = $this->mock(LoggerInterface::class);
        $logger->shouldReceive('info')->with('get http message: ' . json_encode($post))->andReturn(null);
        $logger->shouldReceive('error')->with('gateway not on line: modbus')->andReturn(null);

        // 拦截http logger
        $logger->shouldReceive('log')->withAnyArgs()->andReturn(null);
        $loggerFactory = $this->mock(LoggerFactory::class);
        $loggerFactory->shouldReceive('get')->withAnyArgs()->andReturn($logger);

        $this->post('/', $post)->assertOk()->assertSeeText('status');
    }
}
