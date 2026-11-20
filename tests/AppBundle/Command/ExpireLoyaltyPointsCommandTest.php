<?php

namespace Tests\AppBundle\Command;

use AppBundle\Entity\Loyalty\LoyaltyPointsEntry;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Service\Loyalty\LoyaltyPointsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ExpireLoyaltyPointsCommandTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?Customer $customer = null;
    private ?CommandTester $commandTester = null;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $this->customer = new Customer();
        $this->customer->setEmail('loyalty_expiry_' . uniqid() . '@example.com');
        $this->customer->setEmailCanonical($this->customer->getEmail());
        $this->entityManager->persist($this->customer);
        $this->entityManager->flush();

        $application = new Application($kernel);
        $this->commandTester = new CommandTester($application->find('coopcycle:loyalty:expire-points'));
    }

    protected function tearDown(): void
    {
        if (null !== $this->customer && null !== $this->customer->getId()) {
            $this->entityManager->createQuery('DELETE AppBundle\Entity\Loyalty\LoyaltyPointsEntry e WHERE e.customer = :customer')
                ->setParameter('customer', $this->customer)
                ->execute();
            $this->entityManager->createQuery('DELETE AppBundle\Entity\Sylius\Customer c WHERE c = :customer')
                ->setParameter('customer', $this->customer)
                ->execute();
        }

        parent::tearDown();
    }

    private function credit(int $amount, string $expiresAt): LoyaltyPointsEntry
    {
        $entry = LoyaltyPointsEntry::credit($this->customer, $amount, new \DateTime($expiresAt));
        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    private function ledgerFor(string $type): array
    {
        return $this->entityManager->getRepository(LoyaltyPointsEntry::class)
            ->findBy(['customer' => $this->customer, 'type' => $type]);
    }

    public function testSettlesLapsedCreditsAndRecordsThem(): void
    {
        $lapsed = $this->credit(120, '-1 day');
        $live = $this->credit(80, '+30 days');

        $this->commandTester->execute([]);

        $this->entityManager->refresh($lapsed);
        $this->entityManager->refresh($live);

        self::assertSame(0, $lapsed->getRemaining(), 'the lapsed credit is settled');
        self::assertSame(80, $live->getRemaining(), 'the live credit is untouched');

        $expiries = $this->ledgerFor(LoyaltyPointsEntry::TYPE_EXPIRY);
        self::assertCount(1, $expiries);
        self::assertSame(-120, $expiries[0]->getAmount());

        self::assertStringContainsString('120 point(s) across 1 credit(s) expired', $this->commandTester->getDisplay());
    }

    /**
     * The point of the sweep is bookkeeping, not arithmetic: lapsed points
     * are already out of the balance before it runs, so running it must
     * leave the balance exactly where it was.
     */
    public function testDoesNotChangeTheBalance(): void
    {
        $this->credit(120, '-1 day');
        $this->credit(80, '+30 days');

        $manager = self::getContainer()->get(LoyaltyPointsManager::class);

        $before = $manager->getBalance($this->customer);
        $this->commandTester->execute([]);
        $after = $manager->getBalance($this->customer);

        self::assertSame(80, $before);
        self::assertSame($before, $after);
    }

    public function testOnlySettlesACreditOnce(): void
    {
        $this->credit(120, '-1 day');

        $this->commandTester->execute([]);
        $this->commandTester->execute([]);

        self::assertCount(1, $this->ledgerFor(LoyaltyPointsEntry::TYPE_EXPIRY));
        self::assertStringContainsString('0 point(s) across 0 credit(s)', $this->commandTester->getDisplay());
    }

    public function testDryRunWritesNothing(): void
    {
        $lapsed = $this->credit(120, '-1 day');

        $this->commandTester->execute(['--dry-run' => true]);

        $this->entityManager->refresh($lapsed);

        self::assertSame(120, $lapsed->getRemaining());
        self::assertCount(0, $this->ledgerFor(LoyaltyPointsEntry::TYPE_EXPIRY));
        self::assertStringContainsString('[dry-run] 120 point(s) across 1 credit(s) would be expired', $this->commandTester->getDisplay());
    }

    public function testLeavesCreditsThatNeverExpireAlone(): void
    {
        $forever = LoyaltyPointsEntry::credit($this->customer, 50, null);
        $this->entityManager->persist($forever);
        $this->entityManager->flush();

        $this->commandTester->execute([]);

        $this->entityManager->refresh($forever);

        self::assertSame(50, $forever->getRemaining());
        self::assertCount(0, $this->ledgerFor(LoyaltyPointsEntry::TYPE_EXPIRY));
    }
}
