<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Fixtures\DatabasePurger;
use AppBundle\Service\RfmSegmentCalculator;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CustomerSegmentationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private ?string $rfmEnabled;

    protected function setUp(): void
    {
        parent::setUp();

        // The page is only available when RFM_ENABLED is set
        $this->rfmEnabled = $_ENV['RFM_ENABLED'] ?? null;
        $_ENV['RFM_ENABLED'] = '1';

        $this->client = static::createClient();

        $container = self::getContainer();

        $dbPurger = $container->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();

        $container->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
        ]);

        $calculator = $this->createMock(RfmSegmentCalculator::class);
        $calculator->method('getThresholds')->willReturn([
            'rfm_r_score4_max_days'   => 30,
            'rfm_r_score3_max_days'   => 90,
            'rfm_r_score2_max_days'   => 365,
            'rfm_f_score4_min_orders' => 10,
            'rfm_f_score3_min_orders' => 5,
            'rfm_f_score2_min_orders' => 2,
        ]);
        $calculator->method('computeRows')->willReturn([
            $this->createRow(1, 'alice@example.org', 12, 25000, '-3 days'),
            $this->createRow(2, 'bob@example.org', 30, 90000, '-10 days'),
            $this->createRow(3, 'carol@example.org', 18, 41000, '-1 day'),
        ]);
        $container->set(RfmSegmentCalculator::class, $calculator);

        $userManipulator = $container->get(UserManipulator::class);
        $userManipulator->create('admin', 'password123', 'admin@coopcycle.org', true, false);
        $userManipulator->addRole('admin', 'ROLE_ADMIN');

        $admin = $container->get(UserManagerInterface::class)->findUserByUsername('admin');

        $this->client->loginUser($admin, 'web');
    }

    protected function tearDown(): void
    {
        if (null === $this->rfmEnabled) {
            unset($_ENV['RFM_ENABLED']);
        } else {
            $_ENV['RFM_ENABLED'] = $this->rfmEnabled;
        }

        parent::tearDown();
    }

    public function testKeepsDefaultOrderWhenNotSorted()
    {
        $this->assertSame(
            ['alice@example.org', 'bob@example.org', 'carol@example.org'],
            $this->getCustomerEmails([])
        );
    }

    public function testSortsByMonetaryDescending()
    {
        $this->assertSame(
            ['bob@example.org', 'carol@example.org', 'alice@example.org'],
            $this->getCustomerEmails(['sort' => '[monetary]', 'direction' => 'desc'])
        );
    }

    public function testSortsByMonetaryAscending()
    {
        $this->assertSame(
            ['alice@example.org', 'carol@example.org', 'bob@example.org'],
            $this->getCustomerEmails(['sort' => '[monetary]', 'direction' => 'asc'])
        );
    }

    public function testSortsByFrequency()
    {
        $this->assertSame(
            ['bob@example.org', 'carol@example.org', 'alice@example.org'],
            $this->getCustomerEmails(['sort' => '[frequency]', 'direction' => 'desc'])
        );
    }

    public function testSortsByLastOrder()
    {
        $this->assertSame(
            ['carol@example.org', 'alice@example.org', 'bob@example.org'],
            $this->getCustomerEmails(['sort' => '[last_order_at]', 'direction' => 'desc'])
        );
    }

    public function testRendersSortableColumns()
    {
        $crawler = $this->client->request('GET', '/admin/customers/segmentation', ['segment' => 'champions']);

        $this->assertResponseIsSuccessful();

        $hrefs = $crawler->filter('table thead a')->each(fn ($link) => $link->attr('href'));

        $this->assertCount(3, $hrefs);

        foreach (['[last_order_at]', '[frequency]', '[monetary]'] as $i => $field) {
            parse_str(parse_url($hrefs[$i], PHP_URL_QUERY), $query);

            $this->assertSame('champions', $query['segment']);
            $this->assertSame($field, $query['sort']);
            // The first click shows the most recent, frequent, or spending customers first
            $this->assertSame('desc', $query['direction']);
        }
    }

    private function getCustomerEmails(array $query): array
    {
        $crawler = $this->client->request('GET', '/admin/customers/segmentation', array_merge(['segment' => 'champions'], $query));

        $this->assertResponseIsSuccessful();

        return $crawler->filter('table tbody .text-monospace')->each(fn ($node) => $node->text());
    }

    private function createRow(int $id, string $email, int $frequency, int $monetary, string $lastOrderAt): array
    {
        return [
            'id' => $id,
            'email' => $email,
            'first_name' => null,
            'last_name' => null,
            'username' => null,
            'frequency' => $frequency,
            'monetary' => $monetary,
            'last_order_at' => (new \DateTime($lastOrderAt))->format('Y-m-d H:i:s'),
            'r_score' => 4,
            'f_score' => 4,
            'm_score' => 4,
            'segment' => 'champions',
        ];
    }
}
