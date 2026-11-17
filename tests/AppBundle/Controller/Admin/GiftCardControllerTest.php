<?php

namespace Tests\AppBundle\Controller\Admin;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GiftCardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private ?User $admin = null;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        // Clear first: the identity map still holds entities whose rows are
        // about to go, and flushing them back would resurrect them.
        $this->entityManager->clear();

        foreach ($this->entityManager->getRepository(GiftCard::class)->findAll() as $giftCard) {
            $this->entityManager->remove($giftCard);
        }

        if (null !== $this->admin) {
            $admin = $this->entityManager->getRepository(User::class)->find($this->admin->getId());
            if (null !== $admin) {
                $this->entityManager->remove($admin);
            }
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    private function logInAsAdmin(): void
    {
        $userManager = static::getContainer()->get('nucleos_user.user_manager');

        // Persisted rather than mocked: the `web` firewall reloads the user
        // from the database on each request.
        $admin = $userManager->createUser();
        $admin->setUsername('gift_card_admin');
        $admin->setEmail('gift_card_admin@coopcycle.org');
        $admin->setPlainPassword('123456');
        $admin->setEnabled(true);
        $admin->addRole('ROLE_ADMIN');

        $userManager->updateUser($admin);

        $this->admin = $admin;
        $this->client->loginUser($admin);
    }

    public function testTheListIsAdminOnly()
    {
        $this->client->request('GET', '/admin/gift-cards');

        $this->assertResponseStatusCodeSame(302, 'An anonymous visitor is sent to the login page');
    }

    public function testTheListRenders()
    {
        $this->logInAsAdmin();

        $this->client->request('GET', '/admin/gift-cards');

        $this->assertResponseIsSuccessful();
    }

    public function testIssuingACardByHandCreatesAnActiveGrantedCard()
    {
        $this->logInAsAdmin();

        $crawler = $this->client->request('GET', '/admin/gift-cards/new');
        $this->assertResponseIsSuccessful();

        // Selected by its name, not its label: the button renders translated,
        // so matching on the key would break the moment the locale changes.
        $form = $crawler->filter('form[name="gift_card_issue"]')->form();
        $form['gift_card_issue[amount]'] = '25.00';
        $form['gift_card_issue[reason]'] = 'Compensation for order #1234';

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(302);

        $this->entityManager->clear();

        $giftCards = $this->entityManager->getRepository(GiftCard::class)->findAll();

        $this->assertCount(1, $giftCards);

        $giftCard = $giftCards[0];

        $this->assertSame(GiftCard::STATE_ACTIVE, $giftCard->getState());
        $this->assertSame(2500, $giftCard->getInitialAmount());
        $this->assertSame(2500, $giftCard->getBalance());
        // No charge behind it: whatever it buys, the co-op pays for.
        $this->assertSame(GiftCard::FUNDING_GRANTED, $giftCard->getFunding());
        $this->assertNull($giftCard->getPurchaseChargeId());
        $this->assertCount(1, $giftCard->getTransactions());
    }
}
