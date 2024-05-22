<?php

namespace Tests\AppBundle\Transporter;

use AppBundle\Command\SyncTransportersCommand;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Store;
use AppBundle\Service\SettingsManager;
use AppBundle\Transporter\ImportFromPoint;
use AppBundle\Transporter\ReportFromCC;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SyncTransportersCommandTest extends KernelTestCase {

    use ProphecyTrait;


    protected EntityManagerInterface $entityManager;
    protected LoaderInterface $fixturesLoader;
    protected Filesystem $syncFs;
    protected Filesystem $edifactFs;
    protected $params;
    protected $settingManager;

    public function setUp(): void
    {

        // SET UP SYMFONY
        parent::setUp();
        self::bootKernel();

        // LOAD FROM CONTAINER & PROPHESIZE
        $this->entityManager = self::$container->get(EntityManagerInterface::class);
        $this->fixturesLoader = self::$container->get('fidry_alice_data_fixtures.loader.doctrine');
        $this->params = $this->prophesize(ParameterBagInterface::class);
        $this->settingManager = $this->prophesize(SettingsManager::class);
        $this->syncFs = new Filesystem(new InMemoryFilesystemAdapter());
        $this->edifactFs = new Filesystem(new InMemoryFilesystemAdapter());


        // LOAD AND PERSIST FIXTURES
        $entities = $this->fixturesLoader->load([
            __DIR__.'/../../../features/fixtures/ORM/stores.yml'
        ]);

        array_walk($entities, function($entity) {
            if (!($entity instanceof GeoCoordinates)) {
                $this->entityManager->persist($entity);
            }
        });


        // SET UP SETTINGS
        $this->settingManager
            ->get('latlng')
            ->willReturn('48.8534,2.3488');
        $this->settingManager
            ->get('company_legal_name')
            ->willReturn('Coopcycle Testing Inc.');
        $this->settingManager
            ->get('company_legal_id')
            ->willReturn('4447190000');
        $this->params
            ->get('transporters_config')
            ->willReturn([
                'DBSCHENKER' => [
                    'enabled' => true,
                    'name' => 'DBSchenker test',
                    'legal_name' => 'DBSchenker Testing Inc.',
                    'legal_id' => '0000011',
                    'fs_mask' => 'testing',
                    'sync_uri' => $this->syncFs,
                ]
            ]);

    }

    public function testExcecute(): void
    {
        $command = new SyncTransportersCommand(
            $this->entityManager,
            $this->params->reveal(),
            $this->settingManager->reveal(),
            self::$container->get(ImportFromPoint::class),
            self::$container->get(ReportFromCC::class),
            $this->edifactFs
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 0 tasks', $output);
    }



}
