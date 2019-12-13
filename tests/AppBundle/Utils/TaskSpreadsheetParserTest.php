<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Address;
use AppBundle\Entity\Tag;
use AppBundle\Service\Geocoder;
use AppBundle\Service\TagManager;
use AppBundle\Utils\TaskSpreadsheetParser;
use Cocur\Slugify\Slugify;
use FOS\UserBundle\Model\UserManagerInterface;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class TaskSpreadsheetParserTest extends TestCase
{
    private $geocoder;
    private $tagManager;

    private $parser;

    public function setUp(): void
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->tagManager = $this->prophesize(TagManager::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);
        $this->userManager = $this->prophesize(UserManagerInterface::class);

        $this->bob = new ApiUser();
        $this->bob->addRole('ROLE_COURIER');

        $this->userManager->findUserByUsername('bob')
            ->willReturn($this->bob);

        $this->userManager->findUserByUsername('sarah')
            ->willReturn(null);

        $this->parser = new TaskSpreadsheetParser(
            $this->geocoder->reveal(),
            $this->tagManager->reveal(),
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

        $this->tagManager
            ->fromSlugs(Argument::type('array'))
            ->willReturn([ new Tag() ]);

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
        $this->expectExceptionMessageRegExp('/^Could not geocode address/');

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
        $this->expectExceptionMessageRegExp('/^Could not geocode address/');

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
        $this->expectExceptionMessageRegExp('/^Could not geocode address/');

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
}
