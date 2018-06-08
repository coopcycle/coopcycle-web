<?php

namespace AppBundle\Utils;

use Psr\Log\LoggerInterface;

class ApnsPHPLogger implements \ApnsPHP_Log_Interface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function log($message)
    {
        $this->logger->info($message);
    }
}
