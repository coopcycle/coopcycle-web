<?php

namespace Tests\AppBundle\Controller\Admin;

use AppBundle\Entity\Loyalty\LoyaltyPointsEntry;
use AppBundle\Entity\User;
use AppBundle\Security\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test for the activity list on the admin loyalty dashboard.
 *
 * Same constraints as ReferralProgramControllerTest: the "web" firewall
 * reloads the authenticated user from the DB on every request, so the admin
 * has to be persisted, and entities are re-fetched after each request rather
 * than held across one. LOYALTY_PROGRAM_ENABLED is on in .env.test.
 */
class LoyaltyProgramControllerTest extends WebTestCase
{
    private ?string $adminUsername = null;
    private ?string $customerUsername = null;

    protected function tearDown(): void
    {
        // In a finally, so a failing test still shuts the kernel down: left
        // booted, it makes every later test fail on createClient() instead,
        // hiding which one actually broke.
        try {
            $usernames = array_filter([$this->customerUsername, $this->adminUsername]);

            if ([] !== $usernames) {
                $entityManager = self::getContainer()->get(EntityManagerInterface::class);

                // The entries persisted by the test are still in the identity
                // map, and the DQL DELETE below doesn't take them out of it.
                // Removing their customer with them still managed makes the
                // flush cascade from entry to a removed customer, which
                // Doctrine reports as "a new entity was found".
                $entityManager->clear();

                foreach ($usernames as $username) {
                    $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);

                    if (null !== $user) {
                        $entityManager->createQuery('DELETE AppBundle\Entity\Loyalty\LoyaltyPointsEntry e WHERE e.customer = :customer')
                            ->setParameter('customer', $user->getCustomer())
                            ->execute();
                        $entityManager->remove($user->getCustomer());
                        $entityManager->remove($user);
                        $entityManager->flush();
                    }
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    private function createUser(string $prefix, array $roles): User
    {
        $userManager = self::getContainer()->get(UserManager::class);

        $user = $userManager->createUser();
        $username = $prefix . uniqid();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $user->setPlainPassword('irrelevant');
        $user->setRoles($roles);
        $user->setEnabled(true);
        $userManager->updateUser($user);

        return $user;
    }

    private function loginAsAdmin(): KernelBrowser
    {
        $client = self::createClient();

        $admin = $this->createUser('loyalty_test_admin_', ['ROLE_ADMIN']);
        $this->adminUsername = $admin->getUsername();

        $client->loginUser($admin, 'web');

        return $client;
    }

    private function createCustomerWithEntries(): User
    {
        $user = $this->createUser('loyalty_test_customer_', ['ROLE_USER']);
        $this->customerUsername = $user->getUsername();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Re-read the customer through the manager the entries are persisted
        // with: the one hanging off the User built above belongs to whichever
        // manager UserManager used, and is a detached, "new" entity here.
        $customer = $entityManager->getRepository(User::class)
            ->findOneBy(['username' => $user->getUsername()])
            ->getCustomer();

        $entityManager->persist(LoyaltyPointsEntry::credit($customer, 320, new \DateTime('+90 days')));
        $entityManager->persist(LoyaltyPointsEntry::debit($customer, 150));
        $entityManager->persist(LoyaltyPointsEntry::expiry($customer, 40));
        $entityManager->flush();

        return $user;
    }

    public function testActivityListsEveryCustomersEntries(): void
    {
        $client = $this->loginAsAdmin();
        $customer = $this->createCustomerWithEntries();

        $crawler = $client->request('GET', '/admin/loyalty-program');

        self::assertResponseIsSuccessful();
        // The app under test runs with COOPCYCLE_LOCALE=fr.
        self::assertSelectorTextContains('body', 'Activité');

        $rows = $crawler->filter(sprintf('table tr:contains("%s")', $customer->getEmail()));

        self::assertCount(3, $rows);
        self::assertStringContainsString('+320', $rows->eq(2)->text());
        self::assertStringContainsString('-150', $rows->eq(1)->text());
        self::assertStringContainsString('-40', $rows->eq(0)->text());
    }

    public function testActivityCanBeFilteredByType(): void
    {
        $client = $this->loginAsAdmin();
        $customer = $this->createCustomerWithEntries();

        $crawler = $client->request('GET', '/admin/loyalty-program?type=debit');

        self::assertResponseIsSuccessful();

        $rows = $crawler->filter(sprintf('table tr:contains("%s")', $customer->getEmail()));

        self::assertCount(1, $rows);
        self::assertStringContainsString('-150', $rows->first()->text());
    }

    /**
     * An unknown filter is ignored rather than passed to the query, so a
     * hand-edited URL can't empty the list or break the page.
     */
    public function testUnknownTypeFilterFallsBackToEverything(): void
    {
        $client = $this->loginAsAdmin();
        $customer = $this->createCustomerWithEntries();

        $crawler = $client->request('GET', '/admin/loyalty-program?type=nonsense');

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter(sprintf('table tr:contains("%s")', $customer->getEmail())));
    }

    public function testActivityIsNotVisibleToNonAdmins(): void
    {
        $client = self::createClient();

        $user = $this->createUser('loyalty_test_customer_', ['ROLE_USER']);
        $this->customerUsername = $user->getUsername();

        $client->loginUser($user, 'web');
        $client->request('GET', '/admin/loyalty-program');

        self::assertResponseStatusCodeSame(403);
    }
}
