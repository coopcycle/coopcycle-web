<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Message\ResetRushMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ResetRushModeHandler
{
    public function __construct(
        private string $appName,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger)
    {
    }

    public function __invoke(ResetRushMode $message)
    {
        $this->logger->info(sprintf('Resetting rush mode for instance "%s"', $this->appName));

        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->update(LocalBusiness::class, 'r')
            ->set('r.state', ':state')
            ->setParameter('state', LocalBusiness::STATE_NORMAL);

        $qb->getQuery()->execute();
    }
}
