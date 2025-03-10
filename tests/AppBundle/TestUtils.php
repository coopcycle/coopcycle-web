<?php

namespace Tests\AppBundle;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;

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
}
