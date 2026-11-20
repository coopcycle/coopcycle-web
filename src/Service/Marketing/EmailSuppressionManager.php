<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\EmailSuppression;
use AppBundle\Entity\Marketing\EmailSuppressionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The one way suppressions are written, whether they arrive by webhook or by
 * the periodic sync against Postmark's suppression list.
 */
class EmailSuppressionManager
{
    public function __construct(
        private readonly EmailSuppressionRepository $suppressionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Idempotent: the same bounce can reach us by webhook and again by sync,
     * and Postmark retries webhooks it doesn't get a 200 for.
     */
    public function suppress(
        string $email,
        string $messageStream,
        string $reason,
        ?\DateTime $suppressedAt = null): EmailSuppression
    {
        $existing = $this->suppressionRepository->findOneByEmailAndStream($email, $messageStream);

        if (null !== $existing) {
            // A later, stronger signal (a spam complaint after a bounce) is
            // worth keeping; otherwise leave the original timestamp alone so
            // the record still says when the address first went bad.
            if ($existing->getReason() !== $reason) {
                $existing->setReason($reason);
                $existing->setSuppressedAt($suppressedAt ?? new \DateTime());
                $this->entityManager->flush();
            }

            return $existing;
        }

        $suppression = EmailSuppression::create($email, $messageStream, $reason, $suppressedAt);

        $this->entityManager->persist($suppression);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Two webhook deliveries for the same event racing each other.
            // The unique index is the real guarantee; this just keeps the
            // loser quiet.
            $this->logger->info(sprintf(
                'Suppression for "%s" on stream "%s" already recorded',
                $email,
                $messageStream
            ));
        }

        return $suppression;
    }

    /**
     * Postmark reports reactivations through the same webhook as
     * suppressions, so an address coming back has to be able to clear the
     * record -- otherwise someone who resubscribes stays invisible to every
     * future audience.
     */
    public function unsuppress(string $email, string $messageStream): void
    {
        $existing = $this->suppressionRepository->findOneByEmailAndStream($email, $messageStream);

        if (null === $existing) {
            return;
        }

        $this->entityManager->remove($existing);
        $this->entityManager->flush();
    }
}
