<?php

namespace Tests\AppBundle;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;

class TestUtils
{
    public static function getConsoleLogger(): LoggerInterface
    {
        $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG);
        $verbosityLevelMap = [
            LogLevel::NOTICE => ConsoleOutput::VERBOSITY_NORMAL,
            LogLevel::INFO => ConsoleOutput::VERBOSITY_NORMAL,
            LogLevel::DEBUG => ConsoleOutput::VERBOSITY_VERBOSE,
        ];
        return new ConsoleLogger($output, $verbosityLevelMap);
    }

    public static function consumeMessages(ContainerInterface $container, int $timeLimitInSeconds = 5) {
        $transport = $container->get('messenger.transport.async');
        $bus = $container->get(MessageBusInterface::class);

        // Create an event dispatcher with a listener to stop after processing all messages
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimitInSeconds));

        // Create and run the worker
        $worker = new Worker([$transport], $bus, $eventDispatcher);
        $worker->run([
            'sleep' => 0, // Don't sleep between messages
        ]);
    }
}
