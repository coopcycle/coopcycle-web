<?php

namespace Tests\AppBundle\Command;

use AppBundle\Command\BackfillReferralCodesCommand;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\User;
use AppBundle\Security\UserManager;
use AppBundle\Service\Referral\ReferralCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Functional (real DB) test -- the command drives Doctrine's batch
 * iteration/flush/clear directly, which isn't worth mocking out. Re-fetches
 * the customer by id rather than refresh()ing the original reference,
 * since the command may clear() the EntityManager mid-run and detach it.
 */
class BackfillReferralCodesCommandTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private array $createdUsernames = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);

        foreach ($this->createdUsernames as $username) {
            $user = $userRepository->findOneBy(['username' => $username]);

            if (null !== $user) {
                $this->entityManager->remove($user->getCustomer());
                $this->entityManager->remove($user);
            }
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    private function createCustomerWithoutReferralCode(): Customer
    {
        $userManager = self::getContainer()->get(UserManager::class);

        $user = $userManager->createUser();
        $username = 'backfill_test_' . uniqid();
        $this->createdUsernames[] = $username;

        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPlainPassword('irrelevant');
        $user->setEnabled(true);
        $userManager->updateUser($user);

        self::assertNull($user->getCustomer()->getReferralCode());

        return $user->getCustomer();
    }

    private function command(): CommandTester
    {
        return new CommandTester(new BackfillReferralCodesCommand(
            $this->entityManager,
            self::getContainer()->get(ReferralCodeGenerator::class)
        ));
    }

    public function testGeneratesACodeForCustomersMissingOne(): void
    {
        $customer = $this->createCustomerWithoutReferralCode();
        $customerId = $customer->getId();

        $exitCode = $this->command()->execute([]);

        $this->assertSame(0, $exitCode);

        $refreshed = $this->entityManager->getRepository(Customer::class)->find($customerId);
        $this->assertNotNull($refreshed->getReferralCode());
    }

    public function testDryRunDoesNotWriteAnything(): void
    {
        $customer = $this->createCustomerWithoutReferralCode();
        $customerId = $customer->getId();

        $tester = $this->command();
        $tester->execute(['--dry-run' => true]);

        $this->assertStringContainsString('[dry-run]', $tester->getDisplay());

        $refreshed = $this->entityManager->getRepository(Customer::class)->find($customerId);
        $this->assertNull($refreshed->getReferralCode());
    }
}
