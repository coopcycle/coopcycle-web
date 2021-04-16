<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\User;
use AppBundle\Entity\Address;
use AppBundle\Entity\Tag;
use AppBundle\Service\Geocoder;
use AppBundle\Spreadsheet\AbstractSpreadsheetParser;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Cocur\Slugify\Slugify;
use Nucleos\UserBundle\Model\UserManagerInterface;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Prophecy\Argument;

class TaskSpreadsheetParserTest extends TestCase
{
    private $geocoder;

    protected function createParser(): AbstractSpreadsheetParser
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);
        $this->userManager = $this->prophesize(UserManagerInterface::class);

        $this->bob = new User();
        $this->bob->addRole('ROLE_COURIER');

        $this->userManager->findUserByUsername('bob')
            ->willReturn($this->bob);

        $this->userManager->findUserByUsername('sarah')
            ->willReturn(null);

        return new TaskSpreadsheetParser(
            $this->geocoder->reveal(),
            new Slugify(),
            $this->phoneNumberUtil->reveal(),
            $this->userManager->reveal(),
            'fr'
        );
    }

    private function mockDependencies()
    {
        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(new Address());

        $this->geocoder
            ->reverse(Argument::type('float'), Argument::type('float'))
            ->willReturn(new Address());

        $this->phoneNumberUtil
            ->parse(Argument::any(), Argument::type('string'))
            ->willReturn(new PhoneNumber());
    }

    public function testCsv()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.csv');
        $tasks = $this->parser->parse($filename);

        $this->assertCount(5, $tasks);
    }

    public function testCsvSemicolon()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.semicolon.csv');
        $tasks = $this->parser->parse($filename);

        $this->assertCount(5, $tasks);
    }

    public function testCsvWithGeocoderError()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/^Could not geocode address/');

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(null);

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.csv');
        $tasks = $this->parser->parse($filename);
    }

    public function testXlsx()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.xlsx');
        $tasks = $this->parser->parse($filename);

        $this->assertCount(5, $tasks);
    }

    public function testXlsxWithGeocoderError()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/^Could not geocode address/');

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(null);

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.xlsx');
        $tasks = $this->parser->parse($filename);
    }

    public function testOds()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.ods');
        $tasks = $this->parser->parse($filename);

        $this->assertCount(5, $tasks);
    }

    public function testOdsWithGeocoderError()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/^Could not geocode address/');

        $this->geocoder
            ->geocode(Argument::type('string'))
            ->willReturn(null);

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks.ods');
        $tasks = $this->parser->parse($filename);
    }

    public function testCsvWithAssignColumn()
    {
        $this->mockDependencies();

        $filename = realpath(__DIR__ . '/../Resources/spreadsheet/tasks_assign.csv');
        $tasks = $this->parser->parse($filename);

        $this->assertCount(5, $tasks);

        foreach ($tasks as $task) {
            if ($task->getAddress()->getName() === 'Fleurs Express') {
                $this->assertTrue($task->isAssigned());
                $this->assertTrue($task->isAssignedTo($this->bob));
                break;
            }
        }
    }

    public function parseTimeWindowProvider()
    {
        return [
            [
                '05/03/2020 12:00',
                '5/03/2020 14:30',
                new \DateTime('2020-03-05 12:00:00'),
                new \DateTime('2020-03-05 14:30:00')
            ],
            [
                '2020-03-05 12:00',
                '2020-03-05 14:30',
                new \DateTime('2020-03-05 12:00:00'),
                new \DateTime('2020-03-05 14:30:00')
            ],
            [
                '05.03.2020 12:00',
                '5.03.2020 14:30',
                new \DateTime('2020-03-05 12:00:00'),
                new \DateTime('2020-03-05 14:30:00')
            ],
        ];
    }

    /**
     * @dataProvider parseTimeWindowProvider
     */
    public function testParseTimeWindow($afterText, $beforeText, \DateTime $expectedAfter, \DateTime $expectedBefore)
    {
        [ $after, $before ] = TaskSpreadsheetParser::parseTimeWindow([
            'after' => $afterText,
            'before' => $beforeText,
        ], new \DateTime());

        $this->assertEquals($expectedAfter, $after);
        $this->assertEquals($expectedBefore, $before);
    }

    public function testCanParseExampleData()
    {
        $this->mockDependencies();

        parent::testCanParseExampleData();
    }

    public function testValidateHeaderThrowsException()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('You must provide an "address" (alternatively "address.streetAddress") or a "latlong" (alternatively "address.latlng") column');

        $this->mockDependencies();

        $this->parser->validateHeader([
            'foo',
        ]);
    }

    public function testValidateHeaderWithStreetAddress()
    {
        $this->mockDependencies();

        $this->assertNull($this->parser->validateHeader([
            'address.streetAddress',
        ]));
        $this->assertNull($this->parser->validateHeader([
            'address',
        ]));
    }

    public function testValidateHeaderWithStreetAddressAndLatlng()
    {
        $this->mockDependencies();

        $this->assertNull($this->parser->validateHeader([
            'address.streetAddress',
            'address.latlng',
        ]));
        $this->assertNull($this->parser->validateHeader([
            'address',
            'latlong',
        ]));
    }
}
