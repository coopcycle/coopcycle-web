<?php

namespace Tests\AppBundle\Transporter;

use AppBundle\Command\SyncTransportersCommand;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use AppBundle\Service\DeliveryOrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\TaskManager;
use AppBundle\Transporter\ImportFromPoint;
use AppBundle\Transporter\ReportFromCC;
use AppBundle\Transporter\TransporterHelpers;
use Doctrine\ORM\EntityManagerInterface;
use Fidry\AliceDataFixtures\LoaderInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Transporter\TransporterException;

class SyncTransportersCommandTest extends KernelTestCase {

    use ProphecyTrait;

    const EDI_SAMPLE = <<<EDI
    UNA:+,? ' UNB+UNOC:1+123456789:22+987654321:22+240325:1951+2206' UNH+1+SCONTR:3:2:GT:GTF210+ACG' BGM++240325' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC' DTM+DEP+240325' NAD+DP+98765432100010:05++COOPCYCLE TESTING INC' TSR+++3' CAG+P+V' TDT++++3' DOC+730+++ACG+2278663' UNS+D' RFF+CN+JOY0123456789' GID++1:23+1:21' MSE+CGW+15:KG' NAD+CN+++JOHN DOE:ZIMP COMPANY+64 RUE ALEXANDRE DUMAS+PARIS++75+FR' CTA+IC+:JOHN DOE+06 01 02 03 04:AL' NAD+CO+++HOME DEPOT+54 ROUTE DE TREGUIER:BP 8+LOUANNEC++22+FR' DTM+DES+240322' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC+LE BREHAT:ALLEE DES CHATELETS+PLOUFRAGAN++22440+FR' CAG+P+V+++++++++227004' TSR++D:E+3' TXT+DEL+TEL ?: 06 01 02 03 04 POUR PRENDRE UN RENDEZ VOUS DE LIVRAISON' GDS+G+DIVERS' PCI+23' GIN+BN+*2222121907222700470100691001300' DOC+WBL::JOY0123456789+++ACG+70100691+219072' DOC+824+++PRI+FRSBK830689437' UNS+S' UNT+26+1' UNZ+1+2206'
    EDI;

    const EDI_PICKUP_SAMPLE = <<<EDI
    UNA:+.? ' UNB+UNOC:1+311799456:22+423810365:22+251127:1251+3045' UNH+1+PICKUP:3:2:GT:GTF310' BGM++251127+251127' NAD+MS+31179945601800:05++DBSCHENKER TESTING INC' DTM+BOD+251127' NAD+MR+42381036500068:05++COOPCYCLE TESTING INC' NAD+PW+++TEST+ZA DE LA PRADE:RTE DE NANTES+LOMENER++56+FR' DTM+EDD+251127' TSR+E02++3' CAG+P+V' GDS+G+PALETTE' TDT++++3' DOC+ACO+++ACG' UNS+D' RFF+CN+JOY560000410920251001' GID++1:23+1:21' MSE+CGW+100:KG' NAD+IC+31179945601800++SCHENKER FRANCE+PARC D ACTIVITES SUD LANDES+LOMENER++56270+FR' NAD+CN+++OE / TESTS5+64 RUE ALEXANDRE DUMAS+PARIS++75+FR' NAD+PP+++SCHENKER FRANCE / EXPLOITATION' CAG+P+V+++++++++560000' TSR+E02++3' DOC+WBL+++PRI+00000000+219066' DOC+824+++PRI+FRLRT503000000' UNS+S' UNT+25+1' UNZ+1+3045'
    EDI;

    const INVALID_ADDRESS_EDI_SAMPLE = <<<EDI
    UNA:+,? ' UNB+UNOC:1+123456789:22+987654321:22+240325:1951+2206' UNH+1+SCONTR:3:2:GT:GTF210+ACG' BGM++240325' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC' DTM+DEP+240325' NAD+DP+98765432100010:05++COOPCYCLE TESTING INC' TSR+++3' CAG+P+V' TDT++++3' DOC+730+++ACG+2278663' UNS+D' RFF+CN+JOY0123456789' GID++1:23+1:21' MSE+CGW+15:KG' NAD+CN+++JOHN DOE:ZIMP COMPANY+INVALID ADDRESS+VOID CITY++00+FR' NAD+CO+++HOME DEPOT+54 ROUTE DE TREGUIER:BP 8+LOUANNEC++22+FR' DTM+DES+240322' NAD+FW+12345678900935:05++DBSCHENKER TESTING INC+LE BREHAT:ALLEE DES CHATELETS+PLOUFRAGAN++22440+FR' CAG+P+V+++++++++227004' TSR++D:E+3' GDS+G+DIVERS' PCI+23' GIN+BN+*2222121907222700470100691001300' DOC+WBL::JOY0123456789+++ACG+70100691+219072' DOC+824+++PRI+FRSBK830689437' UNS+S' UNT+26+1' UNZ+1+2206'
    EDI;

    const EDI_DISPOR_SAMPLE = <<<EDI
    UNA:+,? ' UNB+UNOC:1+349669192:22+942803198:22+260602:1341+999901484802' UNH+1484802+DISPOR:3:2:GT:GTF110' BGM++1484802' TSR+++3' CAG+P+V' NAD+CO+40017751500048:05++ORLEANS LOG PC LA ROSEE' DTM+DEP+260602' NAD+FW+94280319800020:05++CYCLOGIK' GDS+G+.' TDT++++3' DOC+730+++ACG+78822' UNS+D' RFF+SEN+LACOURSERIETEST' GID++1:23' MSE+CGW+16,000:KG' NAD+CN+++TEST LA COURSERIE+64 RUE ALEXANDRE DUMAS+PARIS++75+FR' CTA++:TEST MTO+06 01 02 03 04:TE+mtor@tel.fr:TM' NAD+OS+++ORLEANS LOG PC LA ROSEE+7 ROUTE DE BOIGNY+SAINT JEAN DE BRAYE++45800+FR' CAG+P+V+++++LRC++++LRC' TSR+++3' TXT+DEL+TEST A SUPPRIMER' GDS+G+TEST' PCI+23' GIN+BN+*69069000000000LRC01699848001300' DOC+WBL+++PRI+1699848' DOC+150+++PRI+LACOURSERIETEST' UNS+S' UNT+27+1484802' UNZ+1+999901484802'
    EDI;

    const PARTIAL_REPORT_EDI_SAMPLE = <<<EDI
    UNB+UNOC:1+4447190000:22+0000011:22+
    NAD+MR+4447190000:5++Coopcycle Testing Inc.'
    NAD+MS+0000011:5++DBSchenker Testing Inc.'
    UNS+D'
    RFF+UNC+JOY0123456789'
    RSJ+MS+AAR+CFM'
    RSJ+MS+MLV+CFM'
    RSJ+MS+LIV+CFM'
    NAD+MR+4447190000:5++Coopcycle Testing Inc.'
    NAD+MS+0000011:5++DBSchenker Testing Inc.'
    UNS+D'
    RFF+UNC+JOY0123456789'
    RSJ+MS+LIV+CFM'
    EDI;

    const PARTIAL_REPORT_PICKUP_EDI_SAMPLE = <<<EDI
    UNB+UNOC:1+4447190000:22+0000011:22+
    NAD+MR+4447190000:5++Coopcycle Testing Inc.'
    NAD+MS+0000011:5++DBSchenker Testing Inc.'
    UNS+D'
    RFF+UNC+JOY560000410920251001'
    RSJ+MS+AAR+CFM'
    RSJ+MS+MLV+CFM'
    RSJ+MS+LIV+CFM'
    NAD+MR+4447190000:5++Coopcycle Testing Inc.'
    NAD+MS+0000011:5++DBSchenker Testing Inc.'
    UNS+D'
    RFF+UNC+JOY560000410920251001'
    RSJ+MS+LIV+CFM'
    EDI;

    const FS_MASK_DBS = 'testingdbs';
    const FS_MASK_TALIAE = 'testingtaliae';

    protected EntityManagerInterface $entityManager;
    protected TaskManager $taskManager;
    protected LoggerInterface $logger;
    protected LoaderInterface $fixturesLoader;
    protected Filesystem $syncDBSchenkerFs;
    protected Filesystem $syncInBMVFs;
    protected Filesystem $syncOutBMVFs;
    protected Filesystem $syncTaliaeFs;
    protected Filesystem $edifactFs;
    protected DeliveryOrderManager $deliveryOrderManager;
    protected $params;
    protected $settingManager;

    public function setUp(): void
    {

        // SET UP SYMFONY
        parent::setUp();
        self::bootKernel();

        // LOAD FROM CONTAINER & PROPHESIZE
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->taskManager = self::getContainer()->get(TaskManager::class);
        $this->fixturesLoader = self::getContainer()->get('fidry_alice_data_fixtures.loader.doctrine');
        $this->params = $this->prophesize(ParameterBagInterface::class);
        $this->settingManager = $this->prophesize(SettingsManager::class);
        $this->logger = self::getContainer()->get(LoggerInterface::class);
        $this->syncDBSchenkerFs = new Filesystem(new InMemoryFilesystemAdapter());
        $this->syncInBMVFs = new Filesystem(new InMemoryFilesystemAdapter());
        $this->syncOutBMVFs = new Filesystem(new InMemoryFilesystemAdapter());
        $this->syncTaliaeFs = new Filesystem(new InMemoryFilesystemAdapter());
        $this->edifactFs = new Filesystem(new InMemoryFilesystemAdapter());
        $this->deliveryOrderManager = self::getContainer()->get(DeliveryOrderManager::class);


        // LOAD AND PERSIST FIXTURES
        $entities = $this->fixturesLoader->load([
            __DIR__.'/../../../fixtures/ORM/settings_mandatory.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_taxation.yml',
            __DIR__.'/../../../fixtures/ORM/sylius_products.yml',
            __DIR__.'/../../../fixtures/ORM/stores.yml'
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
                    'sync' => [
                        'filemask' => self::FS_MASK_DBS,
                        'uri' => $this->syncDBSchenkerFs,
                    ]
                ],
                'BMV' => [
                    'enabled' => true,
                    'name' => 'BMV test',
                    'legal_name' => 'BMV Testing Inc.',
                    'legal_id' => '0000022',
                    'sync' => [
                        'in' => [
                            'uri' => $this->syncInBMVFs
                        ],
                        'out' => [
                            'uri' => $this->syncOutBMVFs
                        ]
                    ]
                ],
                'TELIAE' => [
                    'enabled' => true,
                    'name' => 'Taliae test',
                    'legal_name' => 'Taliae Testing Inc.',
                    'legal_id' => '0000044',
                    'sync' => [
                        'filemask' => self::FS_MASK_TALIAE,
                        'uri' => $this->syncTaliaeFs,
                    ]
                ]
            ]);

        $this->syncDBSchenkerFs->createDirectory(sprintf('to_%s', self::FS_MASK_DBS));
        $this->syncDBSchenkerFs->createDirectory(sprintf('from_%s', self::FS_MASK_DBS));
        $this->syncTaliaeFs->createDirectory(sprintf('to_%s', self::FS_MASK_TALIAE));
        $this->syncTaliaeFs->createDirectory(sprintf('from_%s', self::FS_MASK_TALIAE));
    }

    protected function initCommand(): Command
    {
        return new SyncTransportersCommand(
            'test',
            $this->entityManager,
            $this->params->reveal(),
            $this->settingManager->reveal(),
            $this->logger,
            self::getContainer()->get(ImportFromPoint::class),
            self::getContainer()->get(ReportFromCC::class),
            $this->edifactFs,
            $this->deliveryOrderManager
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
                    'sync' => [
                        'filemask' => self::FS_MASK_DBS,
                        'uri' => $this->syncDBSchenkerFs,
                    ]
                ]
            ]);

        $command = new SyncTransportersCommand(
            'test',
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            $this->logger,
            self::getContainer()->get(ImportFromPoint::class),
            self::getContainer()->get(ReportFromCC::class),
            $this->edifactFs,
            $this->deliveryOrderManager,
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
                    'sync' => [
                        'filemask' => self::FS_MASK_DBS,
                        'uri' => $this->syncDBSchenkerFs,
                    ]
                ]
            ]);

        $command = new SyncTransportersCommand(
            'test',
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            $this->logger,
            self::getContainer()->get(ImportFromPoint::class),
            self::getContainer()->get(ReportFromCC::class),
            $this->edifactFs,
            $this->deliveryOrderManager,
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
                    'sync' => [
                        'filemask' => self::FS_MASK_DBS,
                        'uri' => $this->syncDBSchenkerFs,
                    ]
                ]
            ]);

        $command = new SyncTransportersCommand(
            'test',
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            $this->logger,
            self::getContainer()->get(ImportFromPoint::class),
            self::getContainer()->get(ReportFromCC::class),
            $this->edifactFs,
            $this->deliveryOrderManager,
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
                    'sync' => [
                        'filemask' => self::FS_MASK_DBS,
                        'uri' => $this->syncDBSchenkerFs,
                    ]
                ]
            ]);

        $command = new SyncTransportersCommand(
            'test',
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            $this->logger,
            self::getContainer()->get(ImportFromPoint::class),
            self::getContainer()->get(ReportFromCC::class),
            $this->edifactFs,
            $this->deliveryOrderManager,
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
                'HEPPNER' => [
                    'enabled' => true,
                    'name' => 'Heppner test',
                    'legal_name' => 'Heppner Testing Inc.',
                    'legal_id' => '0000033'
                ]
            ]);

        $command = new SyncTransportersCommand(
            'test',
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            $this->logger,
            self::getContainer()->get(ImportFromPoint::class),
            self::getContainer()->get(ReportFromCC::class),
            $this->edifactFs,
            $this->deliveryOrderManager,
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No store with transporter "HEPPNER" connected');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'HEPPNER'
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
        $this->syncDBSchenkerFs->write(
            sprintf('to_%s/test.edi', self::FS_MASK_DBS),
            self::INVALID_ADDRESS_EDI_SAMPLE
        );

        // Valid the file is there
        $dir_list = $this->syncDBSchenkerFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
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
        $dir_list = $this->syncDBSchenkerFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
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
        $this->syncDBSchenkerFs->write(
            sprintf('to_%s/test.edi', self::FS_MASK_DBS),
            self::EDI_SAMPLE
        );

        // Valid the file is there
        $dir_list = $this->syncDBSchenkerFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
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
        $dir_list = $this->syncDBSchenkerFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
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
            'Rue Alexandre Dumas 64, 75011 Paris',
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


        $this->assertCount(0, $this->syncDBSchenkerFs->listContents(sprintf('from_%s', self::FS_MASK_DBS))->toArray());
        $this->assertCount(0, $this->syncOutBMVFs->listContents('/')->toArray());

        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('DBSCHENKER');
        $this->assertCount(3, $unsynced);

        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 0 tasks', $output);
        $this->assertStringContainsString('3 messages to send', $output);


        $this->assertCount(0, $this->syncOutBMVFs->listContents('/')->toArray());
        $dir_list = $this->syncDBSchenkerFs->listContents(sprintf('from_%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(1, $dir_list);
        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('DBSCHENKER');
        $this->assertCount(0, $unsynced);


        $reportContent = $this->syncDBSchenkerFs->read($dir_list[0]['path']);

        //FIXME: Should chop-chop while iterative to be sure the order is respected.
        foreach(explode("\n", self::PARTIAL_REPORT_EDI_SAMPLE) as $line) {
            $this->assertStringContainsString(
                $line,
                $reportContent
            );
        }

    }

    public function testValidSyncOnePickupTask(): void
    {
        // Insert edi to sync
        $this->syncDBSchenkerFs->write(
            sprintf('to_%s/test.edi', self::FS_MASK_DBS),
            self::EDI_PICKUP_SAMPLE
        );

        // Valid the file is there
        $dir_list = $this->syncDBSchenkerFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
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
        $dir_list = $this->syncDBSchenkerFs->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
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
            $dropoff->getAddress()->getStreetaddress()
        );
        $this->assertEquals(new GeoCoordinates(48.864577, 2.333338), $dropoff->getAddress()->getGeo());



        $this->assertEquals(
            'Rue Alexandre Dumas 64, 75011 Paris',
            $pickup->getAddress()->getStreetaddress()
        );
        $this->assertEquals(new GeoCoordinates(48.854034, 2.395023), $pickup->getAddress()->getGeo());

        $this->assertEquals(
            'OE / TESTS5',
            $pickup->getAddress()->getCompany()
        );

        $this->assertEquals(100000, $delivery->getWeight());
        $this->assertEquals($pickup, $dropoff->getPrevious());

        $ediMessage = $dropoff->getImportMessage();
        $this->assertNotNull($ediMessage);
        $this->assertEquals('JOY560000410920251001', $ediMessage->getReference());
        $this->assertEquals('DBSCHENKER', $ediMessage->getTransporter());
        $this->assertEquals(
            EDIFACTMessage::DIRECTION_INBOUND,
            $ediMessage->getDirection()
        );

        $this->assertEquals(
            EDIFACTMessage::MESSAGE_TYPE_PICKUP,
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
                [EDIFACTMessage::MESSAGE_TYPE_PICKUP, null],
                [EDIFACTMessage::MESSAGE_TYPE_REPORT, 'AAR|CFM'],
                [EDIFACTMessage::MESSAGE_TYPE_REPORT, 'MLV|CFM'],
            ],
            $pickupReportEDIMessage->toArray()
        );

        /** @var EDIFACTMessage $reportEDIMessage */
        $dropoffReportEDIMessage = $dropoff->getEdifactMessages()->last();

        $this->assertEquals('JOY560000410920251001', $dropoffReportEDIMessage->getReference());
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


        $this->assertCount(0, $this->syncDBSchenkerFs->listContents(sprintf('from_%s', self::FS_MASK_DBS))->toArray());
        $this->assertCount(0, $this->syncOutBMVFs->listContents('/')->toArray());

        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('DBSCHENKER');
        $this->assertCount(3, $unsynced);

        $commandTester->execute([
            'transporter' => 'DBSCHENKER'
        ]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 0 tasks', $output);
        $this->assertStringContainsString('3 messages to send', $output);


        $this->assertCount(0, $this->syncOutBMVFs->listContents('/')->toArray());
        $dir_list = $this->syncDBSchenkerFs->listContents(sprintf('from_%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(1, $dir_list);
        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('DBSCHENKER');
        $this->assertCount(0, $unsynced);


        $reportContent = $this->syncDBSchenkerFs->read($dir_list[0]['path']);

        //FIXME: Should chop-chop while iterative to be sure the order is respected.
        foreach(explode("\n", self::PARTIAL_REPORT_PICKUP_EDI_SAMPLE) as $line) {
            $this->assertStringContainsString(
                $line,
                $reportContent
            );
        }

    }


    public function testMultipleFilesystemsSync(): void
    {
        // Insert edi to sync
        $this->syncOutBMVFs->write('test.edi', self::EDI_SAMPLE);

        // Valid the file is there
        $dir_list = $this->syncOutBMVFs->listContents('/')->toArray();
        $this->assertCount(1, $dir_list);

        $command = $this->initCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'BMV'
        ]);

        // Check command output
        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 1 tasks', $output);
        $this->assertStringContainsString('No messages to send', $output);

        // Check if command removed the file to sync
        $dir_list = $this->syncOutBMVFs->listContents('/')->toArray();
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


        $ediMessage = $dropoff->getImportMessage();

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

        $commandTester->execute([
            'transporter' => 'BMV'
        ]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 0 tasks', $output);
        $this->assertStringContainsString('3 messages to send', $output);

        $this->assertCount(0, $this->syncOutBMVFs->listContents('/')->toArray());
        $dir_list = $this->syncInBMVFs->listContents('/')->toArray();
        $this->assertCount(1, $dir_list);
        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('BMV');
        $this->assertCount(0, $unsynced);
    }

    public function testValidSyncOneDisporTask(): void
    {
        // Insert edi to sync
        $this->syncTaliaeFs->write(
            sprintf('to_%s/test.edi', self::FS_MASK_TALIAE),
            self::EDI_DISPOR_SAMPLE
        );

        // Valid the file is there
        $dir_list = $this->syncTaliaeFs->listContents(sprintf('to_%s', self::FS_MASK_TALIAE))->toArray();
        $this->assertCount(1, $dir_list);

        $command = $this->initCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'transporter' => 'TELIAE'
        ]);

        // Check command output
        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 1 tasks', $output);
        $this->assertStringContainsString('No messages to send', $output);

        // Check if command removed the file to sync
        $dir_list = $this->syncTaliaeFs->listContents(sprintf('to_%s', self::FS_MASK_TALIAE))->toArray();
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


        $this->assertEquals('Taliae', $delivery->getStore()->getName());

        $this->assertEquals(
            '18, avenue Ledru-Rollin 75012 Paris 12ème',
            $pickup->getAddress()->getStreetaddress()
        );
        $this->assertEquals(new GeoCoordinates(48.864577, 2.333338), $pickup->getAddress()->getGeo());



        $this->assertEquals(
            'Rue Alexandre Dumas 64, 75011 Paris',
            $dropoff->getAddress()->getStreetaddress()
        );
        $this->assertEquals(new GeoCoordinates(48.854034, 2.395023), $dropoff->getAddress()->getGeo());
        $this->assertEquals(
            'Country Code: 33 National Number: 601020304',
            $dropoff->getAddress()->getTelephone()->__toString()
        );
        $this->assertEquals(
            'TEST LA COURSERIE',
            $dropoff->getAddress()->getCompany()
        );
        $this->assertEquals(
            'TEST MTO',
            $dropoff->getAddress()->getContactName()
        );
        $this->assertEquals(
            'TEST A SUPPRIMER',
            $dropoff->getComments()
        );

        $this->assertEquals(16000, $delivery->getWeight());
        $this->assertEquals($pickup, $dropoff->getPrevious());

        $ediMessage = $dropoff->getImportMessage();
        $this->assertNotNull($ediMessage);
        $this->assertEquals('LACOURSERIETEST', $ediMessage->getReference());
        $this->assertEquals('TELIAE', $ediMessage->getTransporter());
        $this->assertEquals(
            EDIFACTMessage::DIRECTION_INBOUND,
            $ediMessage->getDirection()
        );

        $this->assertEquals(
            EDIFACTMessage::MESSAGE_TYPE_DISPOR,
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
                [EDIFACTMessage::MESSAGE_TYPE_DISPOR, null],
                [EDIFACTMessage::MESSAGE_TYPE_REPORT, 'AAR|CFM'],
                [EDIFACTMessage::MESSAGE_TYPE_REPORT, 'MLV|CFM'],
            ],
            $pickupReportEDIMessage->toArray()
        );

        /** @var EDIFACTMessage $reportEDIMessage */
        $dropoffReportEDIMessage = $dropoff->getEdifactMessages()->last();

        $this->assertEquals('LACOURSERIETEST', $dropoffReportEDIMessage->getReference());
        $this->assertEquals('TELIAE', $dropoffReportEDIMessage->getTransporter());
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


        $this->assertCount(0, $this->syncTaliaeFs->listContents(sprintf('from_%s', self::FS_MASK_TALIAE))->toArray());
        $this->assertCount(0, $this->syncOutBMVFs->listContents('/')->toArray());

        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('TELIAE');
        $this->assertCount(3, $unsynced);

        $commandTester->execute([
            'transporter' => 'TELIAE'
        ]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 0 tasks', $output);
        $this->assertStringContainsString('3 messages to send', $output);


        $this->assertCount(0, $this->syncOutBMVFs->listContents('/')->toArray());
        $dir_list = $this->syncTaliaeFs->listContents(sprintf('from_%s', self::FS_MASK_TALIAE))->toArray();
        $this->assertCount(1, $dir_list);
        $unsynced = $this->entityManager->getRepository(EDIFACTMessage::class)->getUnsynced('TELIAE');
        $this->assertCount(0, $unsynced);
    }


    public function testParseSyncOptionsFtpDecodesUrlEncodedCredentials(): void
    {
        // user "user@coop" and password "p@ss:123" require percent-encoding
        // inside the URL, otherwise the '@' would be parsed as host separator
        $uri = 'ftp://user%40coop:p%40ss%3A123@ftp.example.com:21/remote/path';

        $fs = TransporterHelpers::parseSyncOptions($uri);

        $this->assertInstanceOf(Filesystem::class, $fs);

        $adapterRef = new \ReflectionProperty($fs, 'adapter');
        $adapterRef->setAccessible(true);
        $adapter = $adapterRef->getValue($fs);

        $optionsRef = new \ReflectionProperty($adapter, 'connectionOptions');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($adapter);

        $this->assertEquals('user@coop', $options->username());
        $this->assertEquals('p@ss:123', $options->password());
        $this->assertEquals('ftp.example.com', $options->host());
        $this->assertEquals(21, $options->port());
        $this->assertEquals('/remote/path', $options->root());
    }

    public function testParseSyncOptionsFtpRejectsUnencodedAtInUser(): void
    {
        // Sanity check: without urldecode() an unencoded '@' in the user
        // would be misinterpreted by parse_url() and the host/user split
        // would be wrong. This documents the failure mode urldecode() fixes.
        $uri = 'ftp://user%40coop:p%40ss@ftp.example.com:21/remote/path';

        $fs = TransporterHelpers::parseSyncOptions($uri);

        $adapterRef = new \ReflectionProperty($fs, 'adapter');
        $adapterRef->setAccessible(true);
        $adapter = $adapterRef->getValue($fs);

        $optionsRef = new \ReflectionProperty($adapter, 'connectionOptions');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($adapter);

        $this->assertEquals('user@coop', $options->username());
        $this->assertEquals('p@ss', $options->password());
        $this->assertEquals('ftp.example.com', $options->host());
    }

    public function testParseSyncOptionsSftpDecodesUrlEncodedCredentials(): void
    {
        $uri = 'sftp://svc%40acme:s%3Aecret%21@files.example.com:2222/outbox';

        $fs = TransporterHelpers::parseSyncOptions($uri);

        $this->assertInstanceOf(Filesystem::class, $fs);

        // For SFTP, the SftpAdapter wraps a ConnectionProvider that holds
        // the (decoded) host/username/password.
        $adapterRef = new \ReflectionProperty($fs, 'adapter');
        $adapterRef->setAccessible(true);
        $adapter = $adapterRef->getValue($fs);

        $providerRef = new \ReflectionProperty($adapter, 'connectionProvider');
        $providerRef->setAccessible(true);
        $provider = $providerRef->getValue($adapter);

        $hostRef = new \ReflectionProperty($provider, 'host');
        $hostRef->setAccessible(true);
        $this->assertEquals('files.example.com', $hostRef->getValue($provider));

        $usernameRef = new \ReflectionProperty($provider, 'username');
        $usernameRef->setAccessible(true);
        $this->assertEquals('svc@acme', $usernameRef->getValue($provider));

        $passwordRef = new \ReflectionProperty($provider, 'password');
        $passwordRef->setAccessible(true);
        $this->assertEquals('s:ecret!', $passwordRef->getValue($provider));

        $portRef = new \ReflectionProperty($provider, 'port');
        $portRef->setAccessible(true);
        $this->assertEquals(2222, $portRef->getValue($provider));
    }

    public function testParseSyncOptionsFtpWithoutPortDefaultsTo21(): void
    {
        $uri = 'ftp://user%40coop:p%40ss@ftp.example.com/remote';

        $fs = TransporterHelpers::parseSyncOptions($uri);

        $adapterRef = new \ReflectionProperty($fs, 'adapter');
        $adapterRef->setAccessible(true);
        $adapter = $adapterRef->getValue($fs);

        $optionsRef = new \ReflectionProperty($adapter, 'connectionOptions');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($adapter);

        $this->assertEquals(21, $options->port());
        $this->assertEquals('/remote', $options->root());
        $this->assertEquals('user@coop', $options->username());
        $this->assertEquals('p@ss', $options->password());
    }


    //
    // TESTING PUSH/PULL PATH TEMPLATES
    //

    /**
     * Builds a SyncTransportersCommand wired to a freshly-prophesied
     * SettingsManager / ParameterBag pair. Used by the path-template
     * tests below, which each need a different `transporters_config`
     * (custom pullPath / pushPath / unknown property / null function).
     */
    private function buildCommandWithConfig(array $transportersConfig): Command
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
            ->willReturn($transportersConfig);

        return new SyncTransportersCommand(
            'test',
            $this->entityManager,
            $params->reveal(),
            $settingManager->reveal(),
            $this->logger,
            self::getContainer()->get(ImportFromPoint::class),
            self::getContainer()->get(ReportFromCC::class),
            $this->edifactFs,
            $this->deliveryOrderManager,
        );
    }

    public function testCustomPullPathTemplateResolvesFilemask(): void
    {
        // Default pullPath for DBSchenker is 'to_{filemask}'. Use a
        // non-default template pointing at a dedicated directory to
        // assert the configured template is what the sync actually uses.
        $customDir = 'inbox';
        $this->syncDBSchenkerFs->createDirectory(sprintf('%s/%s', $customDir, self::FS_MASK_DBS));
        $this->syncDBSchenkerFs->write(
            sprintf('%s/%s/test.edi', $customDir, self::FS_MASK_DBS),
            self::EDI_SAMPLE
        );
        // Sentinel file in the *default* pullPath location: must remain
        // untouched, proving the configured pullPath (not the default) was used.
        $this->syncDBSchenkerFs->write(
            sprintf('to_%s/sentinel.edi', self::FS_MASK_DBS),
            'SENTINEL_SHOULD_NOT_BE_CONSUMED'
        );

        $command = $this->buildCommandWithConfig([
            'DBSCHENKER' => [
                'enabled' => true,
                'name' => 'DBSchenker test',
                'legal_name' => 'DBSchenker Testing Inc.',
                'legal_id' => '0000011',
                'sync' => [
                    'filemask' => self::FS_MASK_DBS,
                    'uri' => $this->syncDBSchenkerFs,
                    'pullPath' => sprintf('%s/{filemask}', $customDir),
                ],
            ],
        ]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['transporter' => 'DBSCHENKER']);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 1 tasks', $output);

        // The file at the custom pullPath was consumed
        $this->assertCount(
            0,
            $this->syncDBSchenkerFs->listContents(sprintf('%s/%s', $customDir, self::FS_MASK_DBS))->toArray()
        );
        // The sentinel in the *default* pullPath is still there, proving
        // the configured custom pullPath — not the default — was used.
        $sentinelList = $this->syncDBSchenkerFs
            ->listContents(sprintf('to_%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(1, $sentinelList);
        $this->assertEquals('sentinel.edi', basename($sentinelList[0]['path']));

        $delivery = $this->entityManager->getRepository(Delivery::class)->findAll();
        $this->assertCount(1, $delivery);
    }

    public function testCustomPushPathTemplateResolvesFilemask(): void
    {
        // Default pushPath for DBSchenker is 'from_{filemask}/{filemask}.{{...}}'.
        // Use a deterministic custom template so we can assert exactly
        // where the report lands and that the default location stays empty.
        $customDir = 'custom_outbox';
        $this->syncDBSchenkerFs->createDirectory(sprintf('to_%s', self::FS_MASK_DBS));
        $this->syncDBSchenkerFs->write(
            sprintf('to_%s/test.edi', self::FS_MASK_DBS),
            self::EDI_SAMPLE
        );

        $command = $this->buildCommandWithConfig([
            'DBSCHENKER' => [
                'enabled' => true,
                'name' => 'DBSchenker test',
                'legal_name' => 'DBSchenker Testing Inc.',
                'legal_id' => '0000011',
                'sync' => [
                    'filemask' => self::FS_MASK_DBS,
                    'uri' => $this->syncDBSchenkerFs,
                    'pushPath' => sprintf('%s/{filemask}/report.edi', $customDir),
                ],
            ],
        ]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['transporter' => 'DBSCHENKER']);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 1 tasks', $output);
        $this->assertStringContainsString('No messages to send', $output);

        // Move tasks along to generate outbound reports
        $delivery = $this->entityManager->getRepository(Delivery::class)->findAll();
        $delivery = array_shift($delivery);
        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();
        $this->taskManager->start($pickup);
        $this->entityManager->flush();
        $this->taskManager->markAsDone($pickup);
        $this->entityManager->flush();
        $this->taskManager->start($dropoff);
        $this->entityManager->flush();
        $this->taskManager->markAsDone($dropoff);
        $this->entityManager->flush();

        $commandTester->execute(['transporter' => 'DBSCHENKER']);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('3 messages to send', $output);

        // The default 'from_<filemask>' was NOT used
        $this->assertCount(
            0,
            $this->syncDBSchenkerFs->listContents(sprintf('from_%s', self::FS_MASK_DBS))->toArray()
        );

        // The custom pushPath directory contains exactly one report file
        $files = $this->syncDBSchenkerFs
            ->listContents(sprintf('%s/%s', $customDir, self::FS_MASK_DBS))->toArray();
        $this->assertCount(1, $files);
        $this->assertEquals(
            sprintf('%s/%s/report.edi', $customDir, self::FS_MASK_DBS),
            $files[0]['path']
        );
    }

    public function testPushPathTemplateEvaluatesFunctionExpression(): void
    {
        $this->syncDBSchenkerFs->createDirectory(sprintf('to_%s', self::FS_MASK_DBS));
        $this->syncDBSchenkerFs->write(
            sprintf('to_%s/test.edi', self::FS_MASK_DBS),
            self::EDI_SAMPLE
        );

        // {{date('Ymd')}} is evaluated by PathTemplate, so the file
        // name embeds today's date — we assert that to confirm eval() ran.
        $command = $this->buildCommandWithConfig([
            'DBSCHENKER' => [
                'enabled' => true,
                'name' => 'DBSchenker test',
                'legal_name' => 'DBSchenker Testing Inc.',
                'legal_id' => '0000011',
                'sync' => [
                    'filemask' => self::FS_MASK_DBS,
                    'uri' => $this->syncDBSchenkerFs,
                    'pushPath' => 'reports/{filemask}/REPORT-{{date(\'Ymd\')}}.edi',
                ],
            ],
        ]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['transporter' => 'DBSCHENKER']);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 1 tasks', $output);

        $delivery = $this->entityManager->getRepository(Delivery::class)->findAll();
        $delivery = array_shift($delivery);
        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();
        $this->taskManager->start($pickup);
        $this->entityManager->flush();
        $this->taskManager->markAsDone($pickup);
        $this->entityManager->flush();
        $this->taskManager->start($dropoff);
        $this->entityManager->flush();
        $this->taskManager->markAsDone($dropoff);
        $this->entityManager->flush();

        $commandTester->execute(['transporter' => 'DBSCHENKER']);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('3 messages to send', $output);

        $expectedDate = date('Ymd');
        $files = $this->syncDBSchenkerFs
            ->listContents(sprintf('reports/%s', self::FS_MASK_DBS))->toArray();
        $this->assertCount(1, $files);
        $this->assertStringContainsString($expectedDate, $files[0]['path']);
        $this->assertStringContainsString('REPORT-', $files[0]['path']);
    }

    public function testPullPathTemplateThrowsOnUnknownProperty(): void
    {
        $command = $this->buildCommandWithConfig([
            'DBSCHENKER' => [
                'enabled' => true,
                'name' => 'DBSchenker test',
                'legal_name' => 'DBSchenker Testing Inc.',
                'legal_id' => '0000011',
                'sync' => [
                    'filemask' => self::FS_MASK_DBS,
                    'uri' => $this->syncDBSchenkerFs,
                    // {undefined_property} is not in TransporterSyncOptions attributes
                    'pullPath' => 'inbox/{undefined_property}',
                ],
            ],
        ]);

        $this->expectException(TransporterException::class);
        $this->expectExceptionMessage("PathTemplate: unknown property 'undefined_property'");

        $commandTester = new CommandTester($command);
        $commandTester->execute(['transporter' => 'DBSCHENKER']);
    }

    public function testPullPathTemplateThrowsOnNullFunctionResult(): void
    {
        $command = $this->buildCommandWithConfig([
            'DBSCHENKER' => [
                'enabled' => true,
                'name' => 'DBSchenker test',
                'legal_name' => 'DBSchenker Testing Inc.',
                'legal_id' => '0000011',
                'sync' => [
                    'filemask' => self::FS_MASK_DBS,
                    'uri' => $this->syncDBSchenkerFs,
                    // {{null}} evaluates to null, which PathTemplate rejects
                    'pullPath' => 'inbox/{{null}}',
                ],
            ],
        ]);

        $this->expectException(TransporterException::class);
        $this->expectExceptionMessage("PathTemplate: expression 'null' did not return a usable value");

        $commandTester = new CommandTester($command);
        $commandTester->execute(['transporter' => 'DBSCHENKER']);
    }

    public function testArbitrarySyncConfigKeyIsAvailableAsPathTemplateAttribute(): void
    {
        // The command forwards every sync config key (except uri, pushPath,
        // pullPath) as a PathTemplate attribute. {region} is not a reserved
        // key, just a user-defined one — it must resolve to 'eu-west'.
        $this->syncDBSchenkerFs->createDirectory('inbox/eu-west');
        $this->syncDBSchenkerFs->write(
            'inbox/eu-west/test.edi',
            self::EDI_SAMPLE
        );

        $command = $this->buildCommandWithConfig([
            'DBSCHENKER' => [
                'enabled' => true,
                'name' => 'DBSchenker test',
                'legal_name' => 'DBSchenker Testing Inc.',
                'legal_id' => '0000011',
                'sync' => [
                    'filemask' => self::FS_MASK_DBS,
                    'uri' => $this->syncDBSchenkerFs,
                    'region' => 'eu-west',
                    'pullPath' => 'inbox/{region}',
                ],
            ],
        ]);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['transporter' => 'DBSCHENKER']);

        $commandTester->assertCommandIsSuccessful();
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('imported 1 tasks', $output);

        // The file at the resolved {region} directory was consumed
        $this->assertCount(
            0,
            $this->syncDBSchenkerFs->listContents('inbox/eu-west')->toArray()
        );

        $delivery = $this->entityManager->getRepository(Delivery::class)->findAll();
        $this->assertCount(1, $delivery);
    }
}
