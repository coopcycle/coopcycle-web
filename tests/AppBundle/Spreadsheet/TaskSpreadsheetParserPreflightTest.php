<?php

namespace Tests\AppBundle\Spreadsheet;

use AppBundle\Entity\Address;
use AppBundle\Entity\Package;
use AppBundle\Entity\User;
use AppBundle\Service\Geocoder;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Nucleos\UserBundle\Model\UserManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What can be told about a file without geocoding it.
 *
 * Geocoding is a couple of seconds a row, so every one of these would otherwise be
 * found out hours into a large import, one row per attempt. Each case asserts that the
 * geocoder was never called: that is the point of the pass.
 */
class TaskSpreadsheetParserPreflightTest extends TestCase
{
    use ProphecyTrait;

    private $geocoder;
    private $phoneNumberUtil;
    private $userManager;
    private TaskSpreadsheetParser $parser;

    protected function setUp(): void
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);
        $this->userManager = $this->prophesize(UserManager::class);

        $this->geocoder->geocode(Argument::any())->will(fn () => new Address());
        $this->geocoder->reverse(Argument::cetera())->will(fn () => new Address());
        $this->phoneNumberUtil->parse(Argument::cetera())->willReturn(new PhoneNumber());

        $courier = new User();
        $courier->addRole('ROLE_COURIER');
        $this->userManager->findUserByUsername('bob')->willReturn($courier);
        $this->userManager->findUserByUsername('sarah')->willReturn(new User());
        $this->userManager->findUserByUsername('nobody')->willReturn(null);

        $packageRepository = $this->prophesize(ObjectRepository::class);
        $packageRepository->findOneBy(Argument::any())->willReturn(null);

        $entityManager = $this->prophesize(EntityManagerInterface::class);
        $entityManager->getRepository(Package::class)->willReturn($packageRepository->reveal());

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans(Argument::cetera())->will(fn ($args) => $args[0]);

        $this->parser = new TaskSpreadsheetParser(
            $this->geocoder->reveal(),
            new Slugify(),
            $this->phoneNumberUtil->reveal(),
            $this->userManager->reveal(),
            'fr',
            $entityManager->reveal(),
            $translator->reveal()
        );
    }

    /**
     * @param array<string,string> ...$rows
     */
    private function parse(array $row, array $options = [])
    {
        return $this->parser->parseData([$row], $options);
    }

    private function assertRowIsRejected($result, string $expectedKey): void
    {
        $this->assertTrue($result->hasErrors(), 'the row was accepted');
        $this->assertContains($expectedKey, $result->getSortedErrors()[1] ?? []);
        $this->assertEmpty($result->getData());

        $this->geocoder->geocode(Argument::any())->shouldNotHaveBeenCalled();
        $this->geocoder->reverse(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testRowWithNeitherAnAddressNorCoordinates(): void
    {
        $result = $this->parse(['address' => '', 'latlong' => '']);

        $this->assertRowIsRejected($result, 'import.address.missing');
    }

    /**
     * Unless the caller says a row without one is to be kept, as the API does
     */
    public function testRowWithoutAnAddressIsKeptWhenTheOptionSaysSo(): void
    {
        $result = $this->parse(
            ['address' => '', 'latlong' => ''],
            ['create_task_if_address_not_geocoded' => true]
        );

        $this->assertFalse($result->hasErrors());
    }

    /**
     * floatval('N/A') is 0.0, so this used to be read as the middle of the Atlantic
     */
    public function testCoordinatesThatAreNotNumbers(): void
    {
        $result = $this->parse(['address' => '', 'latlong' => 'N/A']);

        $this->assertRowIsRejected($result, 'import.coordinates.error');
    }

    public function testCoordinatesWithOnlyOneNumber(): void
    {
        $result = $this->parse(['address' => '', 'latlong' => '48.87']);

        $this->assertRowIsRejected($result, 'import.coordinates.error');
    }

    public function testCoordinatesOutsideTheWorld(): void
    {
        $result = $this->parse(['address' => '', 'latlong' => '148.87,2.35']);

        $this->assertRowIsRejected($result, 'import.coordinates.error');
    }

    public function testCoordinatesAreAccepted(): void
    {
        $result = $this->parse(['address' => '', 'latlong' => ' 48.872322 , 2.354433 ']);

        $this->assertFalse($result->hasErrors());
    }

    public function testBothATimeslotAndATimeWindow(): void
    {
        $result = $this->parse([
            'address' => '1, rue de Rivoli Paris',
            'after' => '2018-02-15 09:00',
            'before' => '2018-02-15 10:00',
            'timeslot' => '2018-02-15 09:00 - 2018-02-15 10:00',
        ]);

        $this->assertTrue($result->hasErrors());
        $this->geocoder->geocode(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testAssignToSomebodyWhoDoesNotExist(): void
    {
        $result = $this->parse([
            'address' => '1, rue de Rivoli Paris',
            'assign' => 'nobody:15/02/2018',
        ]);

        $this->assertRowIsRejected($result, 'User with username "nobody" does not exist');
    }

    public function testAssignToSomebodyWhoIsNotACourier(): void
    {
        $result = $this->parse([
            'address' => '1, rue de Rivoli Paris',
            'assign' => 'sarah:15/02/2018',
        ]);

        $this->assertRowIsRejected($result, 'Can\'t assign tasks to user with username "sarah"');
    }

    public function testAssignWithoutADate(): void
    {
        $result = $this->parse([
            'address' => '1, rue de Rivoli Paris',
            'assign' => 'bob',
        ]);

        $this->assertRowIsRejected($result, 'The column "assign" should contain a username and a date, separated by a colon');
    }

    /**
     * Looked up once per distinct value, however many rows it is on
     */
    public function testEachAssigneeIsLookedUpOnce(): void
    {
        $row = ['address' => '1, rue de Rivoli Paris', 'assign' => 'bob:15/02/2018'];

        $this->parser->parseData([$row, $row, $row]);

        $this->userManager->findUserByUsername('bob')->shouldHaveBeenCalledOnce();
    }

    public function testTelephoneThatIsNotAPhoneNumber(): void
    {
        $this->phoneNumberUtil->parse('not a phone number', 'FR')
            ->willThrow(new NumberParseException(NumberParseException::NOT_A_NUMBER, 'nope'));

        $result = $this->parse([
            'address' => '1, rue de Rivoli Paris',
            'address.telephone' => 'not a phone number',
        ]);

        $this->assertRowIsRejected($result, 'import.telephone.error');
    }

    /**
     * ParsePackagesTrait reads slug=quantity; anything else raised out of list(),
     * failing the whole file with a PHP error rather than naming the row
     */
    public function testPackagesThatAreNotSlugEqualsQuantity(): void
    {
        $result = $this->parse([
            'address' => '1, rue de Rivoli Paris',
            'packages' => 'small-box',
        ]);

        $this->assertRowIsRejected($result, 'import.packages.error');
    }

    public function testWeightThatIsNotANumber(): void
    {
        $result = $this->parse([
            'address' => '1, rue de Rivoli Paris',
            'weight' => 'heavy',
        ]);

        $this->assertRowIsRejected($result, 'import.weight.error');
    }

    public function testValueLongerThanItsColumn(): void
    {
        $result = $this->parse([
            'address' => '1, rue de Rivoli Paris',
            'address.name' => str_repeat('a', 256),
        ]);

        $this->assertRowIsRejected($result, 'import.value.too_long');
    }

    /**
     * A task's reference is unique per organization: one collision fails the flush, and
     * with it every row of the file
     */
    public function testTheSameReferenceTwiceInOneFile(): void
    {
        $row = ['address' => '1, rue de Rivoli Paris', 'ref' => 'CMD-001'];

        $result = $this->parser->parseData([$row, $row]);

        $this->assertTrue($result->hasErrors());
        $this->assertContains('import.ref.duplicate', $result->getSortedErrors()[2]);
        $this->geocoder->geocode(Argument::any())->shouldNotHaveBeenCalled();
    }

    /**
     * An unrecognised type quietly became a dropoff. Files imported through the API rely
     * on that, so only the import screen is strict about it.
     */
    public function testAnUnknownTypeIsReportedOnlyWhenAskedFor(): void
    {
        $row = ['address' => '1, rue de Rivoli Paris', 'type' => 'collection'];

        $this->assertFalse($this->parser->parseData([$row])->hasErrors());

        $result = $this->parser->parseData([$row], ['strict_type_column' => true]);

        $this->assertTrue($result->hasErrors());
        $this->assertContains('import.type.error', $result->getSortedErrors()[1]);
    }

    /**
     * A file that says nowhere to go is of no use at all, whatever its rows hold
     */
    public function testFileWithNoAddressColumnAtAll(): void
    {
        $result = $this->parser->parseData([['comments' => 'nothing to go by']]);

        $this->assertTrue($result->hasErrors());
        $this->assertContains('import.columns.one_of.missing', $result->getSortedErrors()[0]);
        $this->geocoder->geocode(Argument::any())->shouldNotHaveBeenCalled();
    }
}
