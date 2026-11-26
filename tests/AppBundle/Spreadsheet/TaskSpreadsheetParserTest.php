<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\User;
use AppBundle\Entity\Address;
use AppBundle\Entity\Package;
use AppBundle\Entity\Tag;
use AppBundle\Service\Geocoder;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Nucleos\UserBundle\Model\UserManager;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Prophecy\Argument;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskSpreadsheetParserTest extends TestCase
{

    protected function createParser(): AbstractSpreadsheetParser
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);
        $this->userManager = $this->prophesize(UserManager::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->bob = new User();
        $this->bob->addRole('ROLE_COURIER');

        $this->userManager->findUserByUsername('bob')
            ->willReturn($this->bob);

        $this->userManager->findUserByUsername('username')
            ->willReturn($this->bob);

        $this->userManager->findUserByUsername('sarah')
            ->willReturn(null);

        $this->packageRepository = $this->prophesize(ObjectRepository::class);

        $this->entityManager
            ->getRepository(Package::class)
            ->willReturn($this->packageRepository->reveal());

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::cetera())->will(fn ($args) => $args[0]);

        return new TaskSpreadsheetParser(
            $this->geocoder->reveal(),
            new Slugify(),
            $this->phoneNumberUtil->reveal(),
            $this->userManager->reveal(),
            'fr',
            $this->entityManager->reveal(),
            $translator->reveal(),
        );
    }

    private function mockDependencies()
    {
        // One address per call, not one shared by every row: a row's name, description
        // and phone number are set on it
        $this->geocoder
            ->geocode(Argument::type('string'))
            ->will(fn () => new Address());

        $this->geocoder
            ->reverse(Argument::type('float'), Argument::type('float'))
            ->will(fn () => new Address());

        $this->phoneNumberUtil
            ->parse(Argument::any(), Argument::type('string'))
            ->willReturn(new PhoneNumber());
    }

    /**
     * @return \AppBundle\Entity\Task[] the tasks of a result, by row number
     */
    private function parseTasks(string $filename): array
    {
        $result = $this->parser->parse($filename);

        $this->assertFalse($result->hasErrors(), sprintf(
            'The file was read with errors: %s',
            json_encode($result->getSortedErrors())
        ));

        return array_map(fn (array $row) => $row['task'], $result->getData());
    }

    public function testCsv()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.csv');

        $this->assertCount(5, $this->parseTasks($filename));
    }

    /**
     * The column is in kilograms. Its value used to be converted and then dropped on the
     * floor, so a file's weights never reached the tasks at all.
     */
    public function testWeightIsReadInKilograms()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.csv');
        $tasks = $this->parseTasks($filename);

        // Row 2, a pickup: weight is not a dropoff's alone
        $this->assertSame(2500, $tasks[1]->getWeight());
        // Row 3 and 4, dropoffs
        $this->assertSame(15000, $tasks[2]->getWeight());
        $this->assertSame(50000, $tasks[3]->getWeight());
        // Row 6, with no weight at all
        $this->assertNull($tasks[5]->getWeight());
    }

    public function testCsvSemicolon()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.semicolon.csv');

        $this->assertCount(5, $this->parseTasks($filename));
    }

    /**
     * A row that can't be geocoded is reported on its row and the rest of the file is
     * still read: a thousand-row file is worth fixing in one go.
     */
    public function testCsvWithGeocoderError()
    {
        $this->mockDependencies();

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(null);

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.csv');
        $result = $this->parser->parse($filename);

        $this->assertTrue($result->hasErrors());
        // Every row of the file says an address, and none of them geocodes
        $this->assertCount(5, $result->getSortedErrors());
        $this->assertSame(['import.address.geocode.error'], $result->getSortedErrors()[2]);
        $this->assertEmpty($result->getData());
    }

    public function testXlsx()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.xlsx');

        $this->assertCount(5, $this->parseTasks($filename));
    }

    public function testXlsxWithGeocoderError()
    {
        $this->mockDependencies();

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(null);

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.xlsx');
        $result = $this->parser->parse($filename);

        $this->assertTrue($result->hasErrors());
        $this->assertEmpty($result->getData());
    }

    public function testOds()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.ods');

        $this->assertCount(5, $this->parseTasks($filename));
    }

    public function testOdsWithGeocoderError()
    {
        $this->mockDependencies();

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(null);

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.ods');
        $result = $this->parser->parse($filename);

        $this->assertTrue($result->hasErrors());
        $this->assertEmpty($result->getData());
    }

    /**
     * The parser reads who a row is assigned to and leaves it at that: appending the task
     * to a courier's list writes to a list that is already saved, which only the caller
     * may do, inside its transaction.
     */
    public function testCsvWithAssignColumn()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks_assign.csv');
        $result = $this->parser->parse($filename);

        $this->assertFalse($result->hasErrors());

        $rows = $result->getData();
        $this->assertCount(5, $rows);

        $assigned = array_values(array_filter(
            $rows,
            fn (array $row) => 'Fleurs Express' === $row['task']->getAddress()->getName()
        ));

        $this->assertCount(1, $assigned);
        $this->assertNotNull($assigned[0]['assign']);

        [ $user, $date ] = $assigned[0]['assign'];
        $this->assertSame($this->bob, $user);
        $this->assertInstanceOf(\DateTime::class, $date);

        // Nothing was written while reading the file
        $this->assertFalse($assigned[0]['task']->isAssigned());
    }

    public function parseTimeWindowProvider()
    {
        return [
            [
                '05/03/2020 12:00',
                '5/03/2020 14:30',
                '',
                new \DateTime('2020-03-05 12:00:00'),
                new \DateTime('2020-03-05 14:30:00')
            ],
            [
                '2020-03-05 12:00',
                '2020-03-05 14:30',
                '',
                new \DateTime('2020-03-05 12:00:00'),
                new \DateTime('2020-03-05 14:30:00')
            ],
            [
                '05.03.2020 12:00',
                '5.03.2020 14:30',
                '',
                new \DateTime('2020-03-05 12:00:00'),
                new \DateTime('2020-03-05 14:30:00')
            ],
            [
                '',
                '',
                '2020-03-05 12:00 - 2020-03-05 14:30',
                new \DateTime('2020-03-05 12:00:00'),
                new \DateTime('2020-03-05 14:30:00')
            ],
        ];
    }

    /**
     * @dataProvider parseTimeWindowProvider
     */
    public function testParseTimeWindow($afterText, $beforeText, $timeslotText, \DateTime $expectedAfter, \DateTime $expectedBefore)
    {
        [ $after, $before ] = TaskSpreadsheetParser::parseTimeWindow([
            'after' => $afterText,
            'before' => $beforeText,
            'timeslot' => $timeslotText,
        ], new \DateTime());

        $this->assertEquals($expectedAfter, $after);
        $this->assertEquals($expectedBefore, $before);
    }

    public function testCanParseExampleData()
    {
        $this->mockDependencies();

        parent::testCanParseExampleData();
    }

    public function testCsvWithAccents()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks_with_accents.csv');

        $this->assertCount(1, $this->parseTasks($filename));

        $this->geocoder->geocode('Calle 181b #16-13, Bogotá, Colombia')->shouldHaveBeenCalled();
    }
}
