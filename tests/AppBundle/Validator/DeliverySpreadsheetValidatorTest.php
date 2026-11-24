<?php

namespace Tests\AppBundle\Validator;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\Geocoder;
use AppBundle\Service\SettingsManager;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Validator\Constraints\DeliverySpreadsheetValidator;
use AppBundle\Validator\Constraints\Spreadsheet as SpreadsheetConstraint;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Oneup\UploaderBundle\Uploader\File\FlysystemFile;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use libphonenumber\PhoneNumberUtil;

/**
 * What the upload checks, now that the columns of a file are matched to the fields they
 * hold on a screen of its own: that the file can be read at all, not what it calls its
 * columns.
 */
class DeliverySpreadsheetValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    private Filesystem $filesystem;
    private DeliverySpreadsheetParser $parser;

    public function setUp(): void
    {
        $geocoder = $this->prophesize(Geocoder::class);
        $address = new Address();
        $address->setGeo(new GeoCoordinates(48.8534, 2.3488));
        $geocoder->geocode(\Prophecy\Argument::any())->willReturn($address);

        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager->get('latlng')->willReturn('48.8534,2.3488');

        $this->parser = new DeliverySpreadsheetParser(
            $geocoder->reveal(),
            PhoneNumberUtil::getInstance(),
            'fr',
            $this->prophesize(EntityManagerInterface::class)->reveal(),
            new Slugify(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            $settingsManager->reveal()
        );

        $this->filesystem = new Filesystem(
            new LocalFilesystemAdapter(realpath(__DIR__ . '/../Resources/spreadsheet/'))
        );

        parent::setUp();
    }

    protected function createValidator(): DeliverySpreadsheetValidator
    {
        return new DeliverySpreadsheetValidator($this->parser);
    }

    private function validate(string $filename): void
    {
        $this->validator->validate(
            new FlysystemFile($filename, $this->filesystem),
            new SpreadsheetConstraint('delivery')
        );
    }

    /**
     * The whole point: a file naming its columns whatever it likes is now accepted, and
     * what each of them holds is asked on the mapping screen
     */
    public function testAFileWithColumnsOfItsOwnIsAccepted()
    {
        $this->validate('deliveries_custom_headers.csv');

        $this->assertNoViolation();
    }

    public function testAFileWithOurOwnColumnsIsStillAccepted()
    {
        $this->validate('deliveries_with_tour.csv');

        $this->assertNoViolation();
    }

    /**
     * Would otherwise come out of the upload form as a 500: loadSpreadsheet() throws
     * on a file it has no reader for, and a validator is not allowed to
     */
    public function testAFileThatIsNotASpreadsheetIsReported()
    {
        $constraint = new SpreadsheetConstraint('delivery');

        $this->validator->validate(
            new FlysystemFile('deliveries_two_sheets.xlsx/not-a-file', $this->filesystem),
            $constraint
        );

        $this->buildViolation($constraint->unreadableMessage)->assertRaised();
    }

    public function testAFileWithoutAHeaderRowIsReported()
    {
        $constraint = new SpreadsheetConstraint('delivery');

        $this->validate('deliveries_no_header.csv');

        $this->buildViolation($constraint->missingHeaderMessage)->assertRaised();
    }

    public function testAFileWithNoRowToImportIsReported()
    {
        $constraint = new SpreadsheetConstraint('delivery');

        $this->validate('deliveries_header_only.csv');

        $this->buildViolation($constraint->emptyMessage)->assertRaised();
    }

    public function testNoFileIsLeftToTheOtherConstraints()
    {
        $this->validator->validate(null, new SpreadsheetConstraint('delivery'));

        $this->assertNoViolation();
    }
}
