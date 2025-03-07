<?php

namespace AppBundle\Integration\Standtrack;

use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;

class StandtrackTaskListener
{

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) { }


    public function prePersist(Task $task, LifecycleEventArgs $args): void {
        if ($task->isPickup()) {
            $this->logger->debug(sprintf('Assigning IUB to task[id=%d]: not applicable', $task->getId()));
            return;
        }

        $store = $task->getDelivery()?->getStore();
        if (!is_null($store) && !empty($store->getStoreGLN())) {
            $iub_code = $this->getIUB();
            $this->logger->debug(sprintf('Assigning IUB to task[id=%d]: %d', $task->getId(), $iub_code));
            $task->setIUB($iub_code);
        }
    }

    private function getIUB(): int
    {
        //TODO: Create a command to setup the IUB range sequence
        // CREATE SEQUENCE IF NOT EXISTS standtrack_iub_seq MINVALUE [MIN] MAXVALUE [MAX];

        return $this->em->getConnection()
            ->executeQuery("SELECT nextval('standtrack_iub_seq')")
            ->fetchOne();
    }

}
