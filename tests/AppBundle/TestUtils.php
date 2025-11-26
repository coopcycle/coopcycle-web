<?php

namespace Tests\AppBundle;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

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

    /**
     * Consume messages from the async transport.
     * This is a wrapper around the coopcycle:messenger:consume-test command.
     */
    public static function consumeMessages(KernelInterface $kernel, int $timeLimitInSeconds = 5): void
    {
        $application = new Application($kernel);
        $command = $application->find('coopcycle:messenger:consume-test');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--time-limit' => $timeLimitInSeconds,
        ]);
    }
}
