<?php

namespace Tests\AppBundle\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use AppBundle\Entity\Delivery\PricingMatrix;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Fixtures\DatabasePurger;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\UserManipulator;

class PricingMatrixFunctionalTest extends ApiTestCase
{
    private $entityManager;
    private int $ruleSetId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $dbPurger = self::getContainer()->get(DatabasePurger::class);
        $dbPurger->purge();
        $dbPurger->resetSequences();

        $fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');
        $fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/settings_mandatory.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_channels.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_products.yml',
        ], $_SERVER);

        $ruleSet = new PricingRuleSet();
        $ruleSet->setName('Matrix rule set');
        $ruleSet->setStrategy('map');

        $this->entityManager->persist($ruleSet);
        $this->entityManager->flush();

        $this->ruleSetId = $ruleSet->getId();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        $this->entityManager = null;
    }

    private function createAdminClient()
    {
        $userManipulator = self::getContainer()->get(UserManipulator::class);
        $jwtManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        $userManager = self::getContainer()->get(UserManagerInterface::class);

        $userManipulator->create('admin_matrix', 'secret', 'admin-matrix@coopcycle.org', true, false);
        $userManipulator->addRole('admin_matrix', 'ROLE_ADMIN');

        $user = $userManager->findUserByUsername('admin_matrix');
        $token = $jwtManager->create($user);

        return static::createClient(defaultOptions: [
            'headers' => [
                'authorization' => 'Bearer '.$token,
            ],
        ]);
    }

    private function matrixPayload(array $cells = null): array
    {
        return [
            'ruleSet' => '/api/pricing_rule_sets/'.$this->ruleSetId,
            'name' => 'NO WASTE',
            'target' => PricingRule::TARGET_TASK,
            'rowAxis' => [
                'variable' => 'delivery.packages.totalVolumeUnits()',
                'entries' => [
                    ['key' => 'r_s', 'label' => 'S', 'min' => 1, 'max' => 1],
                    ['key' => 'r_m', 'label' => 'M', 'min' => 2, 'max' => 3],
                ],
            ],
            'columnAxis' => [
                'variable' => 'zone',
                'addressSource' => 'task',
                'entries' => [
                    ['key' => 'c_z1', 'label' => 'Z1', 'value' => 'Z1'],
                ],
            ],
            'cells' => $cells ?? [
                'r_s:c_z1' => 480,
                'r_m:c_z1' => 720,
            ],
        ];
    }

    private function freshRuleSet(): PricingRuleSet
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(PricingRuleSet::class)->find($this->ruleSetId);
    }

    public function testCreatingAMatrixGeneratesItsRules()
    {
        $client = $this->createAdminClient();

        $client->request('POST', '/api/pricing_matrices', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $this->matrixPayload(),
        ]);

        $this->assertResponseStatusCodeSame(201);

        $ruleSet = $this->freshRuleSet();

        $this->assertCount(2, $ruleSet->getRules());

        $expressions = array_map(
            fn(PricingRule $rule) => $rule->getExpression(),
            $ruleSet->getRules()->toArray()
        );

        $this->assertEquals([
            'delivery.packages.totalVolumeUnits() in 1..1 and in_zone(task.address, "Z1")',
            'delivery.packages.totalVolumeUnits() in 2..3 and in_zone(task.address, "Z1")',
        ], $expressions);

        foreach ($ruleSet->getRules() as $rule) {
            $this->assertTrue($rule->isGenerated());
        }
    }

    public function testUpdatingACellUpdatesItsRuleInPlace()
    {
        $client = $this->createAdminClient();

        $response = $client->request('POST', '/api/pricing_matrices', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $this->matrixPayload(),
        ]);
        $matrixIri = $response->toArray()['@id'];

        $before = $this->freshRuleSet()->getRules()->first();
        $ruleIdBefore = $before->getId();
        $this->assertEquals('480', $before->getPrice());

        $client->request('PUT', $matrixIri, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $this->matrixPayload([
                'r_s:c_z1' => 500,
                'r_m:c_z1' => 720,
            ]),
        ]);

        $this->assertResponseStatusCodeSame(200);

        $after = $this->freshRuleSet()->getRules()->first();

        $this->assertEquals($ruleIdBefore, $after->getId(), 'The rule was recreated instead of updated');
        $this->assertEquals('500', $after->getPrice());
    }

    public function testRemovingARowRemovesOnlyItsRule()
    {
        $client = $this->createAdminClient();

        $response = $client->request('POST', '/api/pricing_matrices', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $this->matrixPayload(),
        ]);
        $matrixIri = $response->toArray()['@id'];

        $payload = $this->matrixPayload(['r_s:c_z1' => 480]);
        $payload['rowAxis']['entries'] = [['key' => 'r_s', 'label' => 'S', 'min' => 1, 'max' => 1]];

        $client->request('PUT', $matrixIri, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertCount(1, $this->freshRuleSet()->getRules());
    }

    public function testDeletingAMatrixDeletesItsRules()
    {
        $client = $this->createAdminClient();

        $response = $client->request('POST', '/api/pricing_matrices', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $this->matrixPayload(),
        ]);
        $matrixIri = $response->toArray()['@id'];

        $client->request('DELETE', $matrixIri);

        $this->assertResponseStatusCodeSame(204);

        $ruleSet = $this->freshRuleSet();
        $this->assertCount(0, $ruleSet->getRules());
        $this->assertCount(0, $ruleSet->getMatrices());
    }

    public function testAnInvalidAxisIsRefused()
    {
        $client = $this->createAdminClient();

        $payload = $this->matrixPayload();
        $payload['rowAxis']['variable'] = 'something.unknown';

        $client->request('POST', '/api/pricing_matrices', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testACellPointingAtNoEntryIsRefused()
    {
        $client = $this->createAdminClient();

        $client->request('POST', '/api/pricing_matrices', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $this->matrixPayload(['r_gone:c_z1' => 480]),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testEditingAGeneratedRuleThroughTheRuleSetIsRefused()
    {
        $client = $this->createAdminClient();

        $client->request('POST', '/api/pricing_matrices', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $this->matrixPayload(),
        ]);

        $rule = $this->freshRuleSet()->getRules()->first();

        $client->request('PUT', '/api/pricing_rule_sets/'.$this->ruleSetId, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'rules' => [
                    [
                        '@id' => '/api/pricing_rules/'.$rule->getId(),
                        'expression' => 'distance > 1000',
                        'price' => '9999',
                        'target' => PricingRule::TARGET_TASK,
                        'position' => 0,
                    ],
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);

        $unchanged = $this->freshRuleSet()->getRules()->first();
        $this->assertEquals('480', $unchanged->getPrice());
    }
}
