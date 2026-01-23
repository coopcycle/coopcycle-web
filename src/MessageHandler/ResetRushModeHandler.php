<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\ResetRushMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ResetRushModeHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger)
    {
    }

    public function __invoke(ResetRushMode $message)
    {
        $this->logger->info('Resetting rush mode');
    }
}
