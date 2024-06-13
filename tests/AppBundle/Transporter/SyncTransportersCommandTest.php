<?php

namespace Tests\AppBundle\Transporter;

use AppBundle\Command\SyncTransportersCommand;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\TaskManager;
use AppBundle\Transporter\ImportFromPoint;
use AppBundle\Transporter\ReportFromCC;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;




class SyncTransportersCommandTest extends KernelTestCase {

    use ProphecyTrait;

    const EDI_SAMPLE = <<<EDI
    UNA:+,? ' UNB+UNOC:1+123456789:22+987654321:22+240325:1951+2206' UNH+1+SCONTR:3:2:GT:GTF210+ACG' BGM++240325' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC' DTM+DEP+240325' NAD+DP+98765432100010:05++COOPCYCLE TESTING INC' TSR+++3' CAG+P+V' TDT++++3' DOC+730+++ACG+2278663' UNS+D' RFF+CN+JOY0123456789' GID++1:23+1:21' MSE+CGW+15:KG' NAD+CN+++JOHN DOE:ZIMP COMPANY+64 RUE ALEXANDRE DUMAS+PARIS++75+FR' CTA+IC+:JOHN DOE+06 01 02 03 04:AL' NAD+CO+++HOME DEPOT+54 ROUTE DE TREGUIER:BP 8+LOUANNEC++22+FR' DTM+DES+240322' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC+LE BREHAT:ALLEE DES CHATELETS+PLOUFRAGAN++22440+FR' CAG+P+V+++++++++227004' TSR++D:E+3' TXT+DEL+TEL ?: 06 01 02 03 04 POUR PRENDRE UN RENDEZ VOUS DE LIVRAISON' GDS+G+DIVERS' PCI+23' GIN+BN+*2222121907222700470100691001300' DOC+WBL::JOY0123456789+++ACG+70100691+219072' DOC+824+++PRI+FRSBK830689437' UNS+S' UNT+26+1' UNZ+1+2206'
    EDI;

    const INVALID_ADDRESS_EDI_SAMPLE = <<<EDI
    UNA:+,? ' UNB+UNOC:1+123456789:22+987654321:22+240325:1951+2206' UNH+1+SCONTR:3:2:GT:GTF210+ACG' BGM++240325' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC' DTM+DEP+240325' NAD+DP+98765432100010:05++COOPCYCLE TESTING INC' TSR+++3' CAG+P+V' TDT++++3' DOC+730+++ACG+2278663' UNS+D' RFF+CN+JOY0123456789' GID++1:23+1:21' MSE+CGW+15:KG' NAD+CN+++JOHN DOE:ZIMP COMPANY+INVALID ADDRESS+VOID CITY++00+FR' NAD+CO+++HOME DEPOT+54 ROUTE DE TREGUIER:BP 8+LOUANNEC++22+FR' DTM+DES+240322' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC+LE BREHAT:ALLEE DES CHATELETS+PLOUFRAGAN++22440+FR' CAG+P+V+++++++++227004' TSR++D:E+3' GDS+G+DIVERS' PCI+23' GIN+BN+*2222121907222700470100691001300' DOC+WBL::JOY0123456789+++ACG+70100691+219072' DOC+824+++PRI+FRSBK830689437' UNS+S' UNT+26+1' UNZ+1+2206'
    EDI;

    const PARTIAL_REPORT_EDI_SAMPLE = <<<EDI
    UNB+UNOC:1+Coopcycle Testing Inc.:22+DBSchenker Testing Inc.:22+
    NAD+MR+4447190000:5+Coopcycle Testing Inc.'
    NAD+MS+0000011:5+DBSchenker Testing Inc.'
    UNS+D'
    RFF+UNC+JOY0123456789'
    RSJ+MS+AAR+CFM'
    RSJ+MS+MLV+CFM'
    RSJ+MS+LIV+CFM'
    NAD+MR+4447190000:5+Coopcycle Testing Inc.'
    NAD+MS+0000011:5+DBSchenker Testing Inc.'
    UNS+D'
    RFF+UNC+JOY0123456789'
    RSJ+MS+LIV+CFM'
    EDI;

    const FS_MASK_DBS = 'testingdbs';
    const FS_MASK_BMV = 'testingbmv';


    protected EntityManagerInterface $entityManager;
    protected TaskManager $taskManager;
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
        $this->taskManager = self::$container->get(TaskManager::class);
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
                    'fs_mask' => self::FS_MASK_DBS,
                    'sync_uri' => $this->syncFs,
                ],
                'BMV' => [
                    'enabled' => true,
                    'name' => 'BMV test',
                    'legal_name' => 'BMV Testing Inc.',
                    'legal_id' => '0000022',
                    'fs_mask' => self::FS_MASK_BMV,
                    'sync_uri' => $this->syncFs,
                ]
            ]);

        $this->syncFs->createDirectory(sprintf('to_%s', self::FS_MASK_DBS));
        $this->syncFs->createDirectory(sprintf('from_%s', self::FS_MASK_DBS));

        $this->syncFs->createDirectory(sprintf('to_%s', self::FS_MASK_BMV));
        $this->syncFs->createDirectory(sprintf('from_%s', self::FS_MASK_BMV));

    }

    protected function initCommand(): Command
    {
        return new SyncTransportersCommand(
            $this->entityManager,
            $this->params->reveal(),
            $this->settingManager->reveal(),
            self::$container->get(ImportFromPoint::class),
            self::$container->get(ReportFromCC::class),
            $this->edifactFs
        );
    }

    //
    // TESTING CONFIGURATIONS
    //

    public function testMissconfiguredInstanceLatLng(): void
    {
        $settingManager = $this->prophesize(SettingsManager::class);
    $settingManager->get('latlng')
            ->willReturn(null);
    $settingManager->get('company_legal_name')
            ->willReturn('Coopcycle Testing Inc.');
    $settingManager->get('company_legal_id')
            ->willReturn('4447190000');
        $params = $this->prophesize(ParameterBagInterface::class);
    $params->get('transporters_config')
            ->willReturn([
                'DBSCHENKER' => [
                    'enabled' => true,
                    'name' => 'DBSchenker test',
                    'legal_name' => 'DBSchenker Testing Inc.',
                    'legal_id' => '0000011',
                    'fs_mask' => self::FS_MASK_DBS,
                    'sync_uri' => $this->syncFs,
                ]
            ]);

        $command = new SyncTransportersCommand(
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            self::$container->get(ImportFromPoint::class),
            self::$container->get(ReportFromCC::class),
            $this->edifactFs
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid lat-lng setting');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);
    }

    public function testMissconfiguredInstanceLegalName(): void
    {
        $settingManager = $this->prophesize(SettingsManager::class);
        $settingManager->get('latlng')
            ->willReturn('48.8534,2.3488');
        $settingManager->get('company_legal_name')
            ->willReturn(null);
        $settingManager->get('company_legal_id')
            ->willReturn('4447190000');
        $params = $this->prophesize(ParameterBagInterface::class);
        $params->get('transporters_config')
            ->willReturn([
                'DBSCHENKER' => [
                    'enabled' => true,
                    'name' => 'DBSchenker test',
                    'legal_name' => 'DBSchenker Testing Inc.',
                    'legal_id' => '0000011',
                    'fs_mask' => self::FS_MASK_DBS,
                    'sync_uri' => $this->syncFs,
                ]
            ]);

        $command = new SyncTransportersCommand(
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            self::$container->get(ImportFromPoint::class),
            self::$container->get(ReportFromCC::class),
            $this->edifactFs
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Company name not set');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);
    }

    public function testMissconfiguredInstanceLegalId(): void
    {
        $settingManager = $this->prophesize(SettingsManager::class);
        $settingManager->get('latlng')
            ->willReturn('48.8534,2.3488');
        $settingManager->get('company_legal_name')
            ->willReturn('Coopcycle Testing Inc.');
        $settingManager->get('company_legal_id')
            ->willReturn(null);
        $params = $this->prophesize(ParameterBagInterface::class);
        $params->get('transporters_config')
            ->willReturn([
                'DBSCHENKER' => [
                    'enabled' => true,
                    'name' => 'DBSchenker test',
                    'legal_name' => 'DBSchenker Testing Inc.',
                    'legal_id' => '0000011',
                    'fs_mask' => self::FS_MASK_DBS,
                    'sync_uri' => $this->syncFs,
                ]
            ]);

        $command = new SyncTransportersCommand(
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            self::$container->get(ImportFromPoint::class),
            self::$container->get(ReportFromCC::class),
            $this->edifactFs
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Company ID not set');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);
    }

    public function testMissconfiguredTransporter(): void
    {
        $settingManager = $this->prophesize(SettingsManager::class);
        $settingManager->get('latlng')
            ->willReturn('48.8534,2.3488');
        $settingManager->get('company_legal_name')
            ->willReturn('Coopcycle Testing Inc.');
        $settingManager->get('company_legal_id')
            ->willReturn('4447190000');
        $params = $this->prophesize(ParameterBagInterface::class);
        $params->get('transporters_config')
            ->willReturn([
                'FOOBAR' => [
                    'enabled' => true,
                    'name' => 'DBSchenker test',
                    'legal_name' => 'DBSchenker Testing Inc.',
                    'legal_id' => '0000011',
                    'fs_mask' => self::FS_MASK_DBS,
                    'sync_uri' => $this->syncFs,
                ]
            ]);

        $command = new SyncTransportersCommand(
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            self::$container->get(ImportFromPoint::class),
            self::$container->get(ReportFromCC::class),
            $this->edifactFs
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DBSCHENKER is not configured or enabled');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);
    }

    public function testNoStoreConfigured(): void
    {
        $settingManager = $this->prophesize(SettingsManager::class);
        $settingManager->get('latlng')
            ->willReturn('48.8534,2.3488');
        $settingManager->get('company_legal_name')
            ->willReturn('Coopcycle Testing Inc.');
        $settingManager->get('company_legal_id')
            ->willReturn('4447190000');
        $params = $this->prophesize(ParameterBagInterface::class);
        $params->get('transporters_config')
            ->willReturn([
                'BMV' => [
                    'enabled' => true,
                    'name' => 'BMV test',
                    'legal_name' => 'BMV Testing Inc.',
                    'legal_id' => '0000022',
                    'fs_mask' => self::FS_MASK_DBS,
                    'sync_uri' => $this->syncFs,
                ]
            ]);

        $command = new SyncTransportersCommand(
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            self::$container->get(ImportFromPoint::class),
            self::$container->get(ReportFromCC::class),
            $this->edifactFs
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No store with transporter "BMV" connected');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'BMV'
        ]);
    }


    //
    // TESTING IMPORT
    //

    public function testValidEmptySync(): void
    {
        $command = $this->initCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 0 tasks', $output);
        $this->assertStringContainsString('No messages to send', $output);
    }

    public function testInvalidAddressSync(): void
    {
        // Insert edi to sync
        $this->syncFs->write(
            sprintf('to_%s/test.edi', self::FS_MASK_DBS),
            self::INVALID_ADDRESS_EDI_SAMPLE
        );

        // Valid the file is there
        $dir_list = $this->syncFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(1, $dir_list);

        $command = $this->initCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 1 tasks', $output);
        $this->assertStringContainsString('No messages to send', $output);

        // Check if command removed the file to sync
        $dir_list = $this->syncFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(0, $dir_list);

        $delivery = $this->entityManager->getRepository(Delivery::class)->findAll();
        $this->assertCount(1, $delivery);

        /** @var Delivery $delivery */
        $delivery = array_shift($delivery);
        $this->assertCount(2, $delivery->getTasks());


        /** @var Task $pickup */
        $pickup = $delivery->getPickup();
        /** @var Task $dropoff */
        $dropoff = $delivery->getDropoff();

        $this->assertEquals(
            '18, avenue Ledru-Rollin 75012 Paris 12ème',
            $pickup->getAddress()->getStreetaddress()
        );
        $this->assertEquals(new GeoCoordinates(48.864577, 2.333338), $pickup->getAddress()->getGeo());

        $this->assertEquals(
            "INVALID ADDRESS",
            $dropoff->getAddress()->getStreetaddress()
        );
        $this->assertEquals(
            'JOHN DOE ZIMP COMPANY',
            $dropoff->getAddress()->getCompany()
        );
        $this->assertContains('review-needed', $dropoff->getTags());

        $this->assertEquals(15000, $delivery->getWeight());

        $ediMessage = $dropoff->getImportMessage();
        $this->assertNotNull($ediMessage);
        $this->assertEquals('JOY0123456789', $ediMessage->getReference());
        $this->assertEquals('DBSCHENKER', $ediMessage->getTransporter());
        $this->assertEquals(
            EDIFACTMessage::DIRECTION_INBOUND,
            $ediMessage->getDirection()
        );

        $this->assertEquals(
            EDIFACTMessage::MESSAGE_TYPE_SCONTR,
            $ediMessage->getMessageType()
        );

    }


    public function testValidSyncOneTask(): void
    {
        // Insert edi to sync
        $this->syncFs->write(
            sprintf('to_%s/test.edi', self::FS_MASK_DBS),
            self::EDI_SAMPLE
        );

        // Valid the file is there
        $dir_list = $this->syncFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(1, $dir_list);

        $command = $this->initCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);

        // Check command output
        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 1 tasks', $output);
        $this->assertStringContainsString('No messages to send', $output);

        // Check if command removed the file to sync
        $dir_list = $this->syncFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(0, $dir_list);

        $delivery = $this->entityManager->getRepository(Delivery::class)->findAll();
        $this->assertCount(1, $delivery);

        /** @var Delivery $delivery */
        $delivery = array_shift($delivery);
        $this->assertCount(2, $delivery->getTasks());


        /** @var Task $pickup */
        $pickup = $delivery->getPickup();
        /** @var Task $dropoff */
        $dropoff = $delivery->getDropoff();


        $this->assertEquals('DBSchenker', $delivery->getStore()->getName());

        $this->assertEquals(
            '18, avenue Ledru-Rollin 75012 Paris 12ème',
            $pickup->getAddress()->getStreetaddress()
        );
        $this->assertEquals(new GeoCoordinates(48.864577, 2.333338), $pickup->getAddress()->getGeo());



        $this->assertEquals(
            '64 Rue Alexandre Dumas, 75011 Paris',
            $dropoff->getAddress()->getStreetaddress()
        );
        $this->assertEquals(new GeoCoordinates(48.854034, 2.395023), $dropoff->getAddress()->getGeo());
        $this->assertEquals(
            'Country Code: 33 National Number: 601020304',
            $dropoff->getAddress()->getTelephone()->__toString()
        );
        $this->assertEquals(
            'JOHN DOE ZIMP COMPANY',
            $dropoff->getAddress()->getCompany()
        );
        $this->assertEquals(
            'JOHN DOE',
            $dropoff->getAddress()->getContactName()
        );
        $this->assertEquals(
            'TEL : 06 01 02 03 04 POUR PRENDRE UN RENDEZ VOUS DE LIVRAISON',
            $dropoff->getComments()
        );

        $this->assertEquals(15000, $delivery->getWeight());
        $this->assertEquals($pickup, $dropoff->getPrevious());

        $ediMessage = $dropoff->getImportMessage();
        $this->assertNotNull($ediMessage);
        $this->assertEquals('JOY0123456789', $ediMessage->getReference());
        $this->assertEquals('DBSCHENKER', $ediMessage->getTransporter());
        $this->assertEquals(
            EDIFACTMessage::DIRECTION_INBOUND,
            $ediMessage->getDirection()
        );

        $this->assertEquals(
            EDIFACTMessage::MESSAGE_TYPE_SCONTR,
            $ediMessage->getMessageType()
        );


        $this->assertCount(1, $dropoff->getEdifactMessages());
        $this->taskManager->start($pickup);
        $this->entityManager->flush();
        $this->taskManager->markAsDone($pickup);
        $this->entityManager->flush();
        $this->taskManager->start($dropoff);
        $this->entityManager->flush();
        $this->taskManager->markAsDone($dropoff);
        $this->entityManager->flush();

        $this->assertCount(3, $pickup->getEdifactMessages());
        $this->assertCount(2, $dropoff->getEdifactMessages());

        $pickupReportEDIMessage = $pickup->getEdifactMessages()->map(function (EDIFACTMessage $message) {
            return [$message->getMessageType(), $message->getSubMessageType()];
        });
        $this->assertEquals(
            [
                [EDIFACTMessage::MESSAGE_TYPE_SCONTR, null],
                [EDIFACTMessage::MESSAGE_TYPE_REPORT, 'AAR|CFM'],
                [EDIFACTMessage::MESSAGE_TYPE_REPORT, 'MLV|CFM'],
            ],
            $pickupReportEDIMessage->toArray()
        );

        /** @var EDIFACTMessage $reportEDIMessage */
        $dropoffReportEDIMessage = $dropoff->getEdifactMessages()->last();

        $this->assertEquals('JOY0123456789', $dropoffReportEDIMessage->getReference());
        $this->assertEquals('DBSCHENKER', $dropoffReportEDIMessage->getTransporter());
        $this->assertEquals(
            EDIFACTMessage::MESSAGE_TYPE_REPORT,
            $dropoffReportEDIMessage->getMessageType()
        );
        $this->assertEquals(
            EDIFACTMessage::DIRECTION_OUTBOUND,
            $dropoffReportEDIMessage->getDirection()
        );
        $this->assertEquals(
            'LIV|CFM',
            $dropoffReportEDIMessage->getSubMessageType()
        );
        $this->assertNull($dropoffReportEDIMessage->getSyncedAt());


        $this->assertCount(0, $this->syncFs->listContents(sprintf('from_%s', self::FS_MASK_DBS))->toArray());
        $this->assertCount(0, $this->syncFs->listContents(sprintf('from_%s', self::FS_MASK_BMV))->toArray());

        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('DBSCHENKER');
        $this->assertCount(3, $unsynced);

        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 0 tasks', $output);
        $this->assertStringContainsString('3 messages to send', $output);


        $this->assertCount(0, $this->syncFs->listContents(sprintf('from_%s', self::FS_MASK_BMV))->toArray());
        $dir_list = $this->syncFs->listContents(sprintf('from_%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(1, $dir_list);
        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('DBSCHENKER');
        $this->assertCount(0, $unsynced);


        $reportContent = $this->syncFs->read($dir_list[0]['path']);

        foreach(explode("\n", self::PARTIAL_REPORT_EDI_SAMPLE) as $line) {
            $this->assertStringContainsString(
                $line,
                $reportContent
            );
        }


    }

}
