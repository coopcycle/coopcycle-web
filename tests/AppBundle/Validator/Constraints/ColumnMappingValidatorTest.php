<?php

namespace Tests\AppBundle\Validator\Constraints;

use AppBundle\Entity\ImportQueue;
use AppBundle\Import\DeliveryImportHandler;
use AppBundle\Import\ImportRegistry;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Validator\Constraints\ColumnMapping;
use AppBundle\Validator\Constraints\ColumnMappingValidator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The mapping screen is where the fields a delivery can't do without are made sure of:
 * once a file is read through a mapping, a column that isn't there is simply not read.
 */
class ColumnMappingValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ColumnMappingValidator
    {
        $parser = $this->createMock(DeliverySpreadsheetParser::class);
        $parser->method('getRequiredColumnKeys')->willReturn([
            'pickup.address',
            'pickup.timeslot',
            'dropoff.address',
            'dropoff.timeslot',
        ]);
        $parser->method('getRequiredColumnGroups')->willReturn([]);

        $importHandler = $this->createMock(DeliveryImportHandler::class);
        $importHandler->method('getParser')->willReturn($parser);

        $registry = new ImportRegistry(
            new ServiceLocator([ImportQueue::TYPE_DELIVERIES => fn () => $importHandler]),
            new ServiceLocator([])
        );

        $translator = $this->createMock(TranslatorInterface::class);
        // The column names are spelled out to the user, the keys are ours
        $translator->method('trans')->willReturnCallback(fn (string $key) => $key);

        return new ColumnMappingValidator($registry, $translator);
    }

    private function fullMapping(): array
    {
        return [
            0 => 'pickup.address',
            1 => 'pickup.timeslot',
            2 => 'dropoff.address',
            3 => 'dropoff.timeslot',
        ];
    }

    public function testAMappingWithEveryRequiredColumnPasses()
    {
        $this->validator->validate($this->fullMapping(), new ColumnMapping());

        $this->assertNoViolation();
    }

    public function testIgnoredColumnsArePassedOver()
    {
        $mapping = $this->fullMapping() + [4 => '', 5 => null, 6 => 'weight'];

        $this->validator->validate($mapping, new ColumnMapping());

        $this->assertNoViolation();
    }

    public function testAMissingRequiredColumnIsReported()
    {
        $mapping = $this->fullMapping();
        unset($mapping[1]);

        $constraint = new ColumnMapping();

        $this->validator->validate($mapping, $constraint);

        $this->buildViolation($constraint->missingColumnsMessage)
            ->setParameter('%columns%', 'import.column.pickup.timeslot')
            ->assertRaised();
    }

    /**
     * More than one column left unmapped: the user is told about all of them at once
     */
    public function testEveryMissingColumnIsNamed()
    {
        $constraint = new ColumnMapping();

        $this->validator->validate([0 => 'weight'], $constraint);

        $this->buildViolation($constraint->missingColumnsMessage)
            ->setParameter('%columns%', implode(', ', [
                'import.column.pickup.address',
                'import.column.pickup.timeslot',
                'import.column.dropoff.address',
                'import.column.dropoff.timeslot',
            ]))
            ->assertRaised();
    }

    /**
     * Two columns of the file for one field: there is no telling which was meant.
     * The screen greys out the fields already taken, but a stale form can still
     * get here, and so could a mapping that comes from somewhere else one day.
     */
    public function testAFieldTakenTwiceIsReported()
    {
        $mapping = $this->fullMapping() + [4 => 'dropoff.address'];

        $constraint = new ColumnMapping();

        $this->validator->validate($mapping, $constraint);

        $this->buildViolation($constraint->duplicateColumnsMessage)
            ->setParameter('%columns%', 'import.column.dropoff.address')
            ->assertRaised();
    }

    public function testNullIsLeftToTheOtherConstraints()
    {
        $this->validator->validate(null, new ColumnMapping());

        $this->assertNoViolation();
    }
}
