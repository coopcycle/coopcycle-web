<?php

namespace AppBundle\Fixtures;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

class DatabasePurger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function purge(): void
    {
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    /**
     * Reset all sequences (auto-generated primary keys) in the database to 1.
     * This is useful for testing purposes to have stable IDs for fixtures across test runs.
     */
    public function resetSequences(): void
    {
        $connection = $this->entityManager->getConnection();
        $rows = $connection->fetchAllAssociative('SELECT sequence_name FROM information_schema.sequences');
        foreach ($rows as $row) {
            $connection->executeQuery(sprintf('ALTER SEQUENCE %s RESTART WITH 1', $row['sequence_name']));
        }
    }

}
