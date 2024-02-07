<?php

namespace AppBundle\Validator;

use AppBundle\Service\Geocoder;
use AppBundle\Spreadsheet\TaskSpreadsheetParser;
use AppBundle\Validator\Constraints\Spreadsheet as SpreadsheetConstraint;
use AppBundle\Validator\Constraints\TaskSpreadsheetValidator;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Adapter\Local as LocalFilesystemAdapter;
use League\Flysystem\Filesystem;
use Nucleos\UserBundle\Model\UserManager;
use Oneup\UploaderBundle\Uploader\File\FlysystemFile;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\ValidatorBuilder;
use libphonenumber\PhoneNumberUtil;

class TaskSpreadsheetValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    public function setUp() :void
    {
        $this->geocoder = $this->prophesize(Geocoder::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);
        $this->userManager = $this->prophesize(UserManager::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->parser = new TaskSpreadsheetParser(
            $this->geocoder->reveal(),
            new Slugify(),
            $this->phoneNumberUtil->reveal(),
            $this->userManager->reveal(),
            'fr',
            $this->entityManager->reveal(),
        );

        $adapter = new LocalFilesystemAdapter(realpath(__DIR__ . '/../Resources/spreadsheet/'));
        $this->filesystem = new Filesystem($adapter);

        parent::setUp();
    }

    protected function createValidator()
    {
        return new TaskSpreadsheetValidator($this->parser);
    }

    public function testMissingHeaders()
    {
        $value = new FlysystemFile($this->filesystem->get('tasks_missing_headers.csv'), $this->filesystem);

        $constraint = new SpreadsheetConstraint('task');
        $violations = $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->missingColumnsMessage)
            ->setParameter('%column%', 'address')
            ->setParameter('%other_column%', 'latlong')
            ->assertRaised();
    }

    public function testValidHeaders()
    {
        $value = new FlysystemFile($this->filesystem->get('tasks.csv'), $this->filesystem);

        $constraint = new SpreadsheetConstraint('task');
        $violations = $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }
}
