<?php

namespace AppBundle\Invoice;

use AppBundle\Entity\Invoice\Sequence;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @see https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/reference/transactions-and-concurrency.html
 * @see https://github.com/Sylius/InvoicingPlugin/blob/master/src/Generator/SequentialInvoiceNumberGenerator.php
 */
final class NumberGenerator
{
    private $objectManager;

    private $sequenceRepository;

    /** @var int */
    private $startNumber;

    /** @var int */
    private $numberLength;

    public function __construct(
        ObjectManager $objectManager,
        int $startNumber = 1,
        int $numberLength = 9)
    {
        $this->objectManager = $objectManager;
        $this->sequenceRepository = $objectManager->getRepository(Sequence::class);
        $this->startNumber = $startNumber;
        $this->numberLength = $numberLength;
    }

    public function generate(): string
    {
        $invoiceIdentifierPrefix = (new \DateTime())->format('Ym');

        $sequence = $this->getSequence();

        $this->objectManager->lock($sequence, LockMode::OPTIMISTIC, $sequence->getVersion());

        $number = $this->generateNumber($sequence->getIndex());
        $sequence->incrementIndex();

        return $invoiceIdentifierPrefix . $number;
    }

    private function generateNumber(int $index): string
    {
        $number = $this->startNumber + $index;

        return str_pad((string) $number, $this->numberLength, '0', \STR_PAD_LEFT);
    }

    private function getSequence(): Sequence
    {
        $sequence = $this->sequenceRepository->findOneBy([]);

        if (null !== $sequence) {
            return $sequence;
        }

        $sequence = new Sequence();
        $this->objectManager->persist($sequence);

        return $sequence;
    }
}
