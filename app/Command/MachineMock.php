<?php

declare(strict_types=1);

namespace App\Command;

use App\Util\Utils;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Coordinator\Timer;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;
use Psr\Container\ContainerInterface;
use Swoole\Coroutine\Client;
use function Hyperf\Support\env;

/**
 * 模拟门闸机器
 * @package App\Command
 */
#[Command]
class MachineMock extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('machine:mock');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Hyperf Mock Command');
    }

    public function handle()
    {
        while (1) {
            $client = new Client(SWOOLE_SOCK_TCP);
            $client->connect('127.0.0.1', 9504);
            $client->send('{"type":"login","key":"' . env('GATEWAY_KEY') . '","name":"modbus"}');

            (new Timer())->tick(10, function () use ($client) {
                $client->send('{"type":"ping","data":1}');
            });
            while (1) {
                $msg = $client->recv(-1);
                if ($msg === false) {
                    $client->close();
                    break;
                }

                if (!Utils::isJson($msg)) {
                    $this->line('recv: ' . bin2hex($msg));
                    $client->send($msg);
                } else {
                    $this->line('recv: ' . $msg);
                }
            }
            Coroutine::sleep(1);
        }
    }
}
