<?php

namespace Tests\AppBundle\Form;

use AppBundle\Form\DeliveryImportMappingType;
use AppBundle\Spreadsheet\Column\ColumnDefinition;
use AppBundle\Spreadsheet\Column\ColumnGroup;
use AppBundle\Spreadsheet\Column\ColumnType;
use AppBundle\Entity\ImportQueue;
use AppBundle\Import\DeliveryImportHandler;
use AppBundle\Import\ImportRegistry;
use AppBundle\Spreadsheet\DeliverySpreadsheetParser;
use AppBundle\Validator\Constraints\ColumnMappingValidator;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * One choice per column of the file, keyed by position, with the fields a delivery can't
 * do without made sure of before the file is handed to a worker.
 */
class DeliveryImportMappingTypeTest extends TypeTestCase
{
    private const HEADER = [
        0 => 'Adresse enlèvement',
        1 => 'Créneau enlèvement',
        2 => 'Adresse livraison',
        3 => 'Créneau livraison',
        // A column the file happens to carry and the import has no use for
        4 => '',
    ];

    protected function getExtensions(): array
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => $key);

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

        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ColumnMappingValidator::class => new ColumnMappingValidator($registry, $translator),
            ]))
            ->getValidator();

        return [
            new PreloadedExtension([new DeliveryImportMappingType($translator)], []),
            new ValidatorExtension($validator),
        ];
    }

    /**
     * @return ColumnDefinition[]
     */
    private function columns(): array
    {
        return [
            new ColumnDefinition('pickup.address', ColumnType::Address, ColumnGroup::Pickup, true),
            new ColumnDefinition('pickup.timeslot', ColumnType::Timeslot, ColumnGroup::Pickup, true),
            new ColumnDefinition('dropoff.address', ColumnType::Address, ColumnGroup::Dropoff, true),
            new ColumnDefinition('dropoff.timeslot', ColumnType::Timeslot, ColumnGroup::Dropoff, true),
            new ColumnDefinition('weight', ColumnType::Number, ColumnGroup::Delivery),
        ];
    }

    private function createMappingForm(array $header = self::HEADER)
    {
        return $this->factory->create(DeliveryImportMappingType::class, null, [
            'columns' => $this->columns(),
            'file_columns' => $header,
        ]);
    }

    private function validMapping(): array
    {
        return [
            '0' => 'pickup.address',
            '1' => 'pickup.timeslot',
            '2' => 'dropoff.address',
            '3' => 'dropoff.timeslot',
            '4' => '',
        ];
    }

    public function testOneChoicePerColumnOfTheFile()
    {
        $form = $this->createMappingForm();

        foreach (array_keys(self::HEADER) as $index) {
            $this->assertTrue($form->has((string) $index), sprintf('column %d has a choice', $index));
        }

        $this->assertCount(count(self::HEADER), $form->all());
    }

    public function testTheChoicesAreGroupedByWhatTheFieldIsAbout()
    {
        $choices = $this->createMappingForm()->get('0')->getConfig()->getOption('choices');

        $this->assertSame([
            'import.column_group.pickup',
            'import.column_group.dropoff',
            'import.column_group.delivery',
        ], array_keys($choices));
    }

    public function testAColumnWithNoHeaderIsNamedByItsPosition()
    {
        $form = $this->createMappingForm();

        $this->assertSame('Adresse enlèvement', $form->get('0')->getConfig()->getOption('label'));
        $this->assertSame('import.mapping.column.unnamed', $form->get('4')->getConfig()->getOption('label'));
    }

    public function testAColumnCanBeLeftOut()
    {
        $form = $this->createMappingForm();

        $this->assertFalse($form->get('4')->isRequired());
        $this->assertSame('import.mapping.ignore', $form->get('4')->getConfig()->getOption('placeholder'));
    }

    public function testSubmittingAMappingWithEveryRequiredColumn()
    {
        $form = $this->createMappingForm();

        $form->submit($this->validMapping());

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        // A column left out comes back as null, which ColumnMapping drops
        $this->assertSame([
            '0' => 'pickup.address',
            '1' => 'pickup.timeslot',
            '2' => 'dropoff.address',
            '3' => 'dropoff.timeslot',
            '4' => null,
        ], $form->getData());
    }

    public function testAMappingMissingARequiredColumnIsRejected()
    {
        $mapping = $this->validMapping();
        $mapping['1'] = '';

        $form = $this->createMappingForm();

        $form->submit($mapping);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->getErrors());
    }

    /**
     * The screen greys out the fields already taken, but a form left open while the
     * options changed can still get here
     */
    public function testAMappingTakingAFieldTwiceIsRejected()
    {
        $mapping = $this->validMapping();
        $mapping['4'] = 'dropoff.address';

        $form = $this->createMappingForm();

        $form->submit($mapping);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->getErrors());
    }

    /**
     * Both at once: the user sees everything that is wrong, not the first thing
     */
    public function testEveryProblemWithAMappingIsReportedAtOnce()
    {
        $form = $this->createMappingForm();

        $form->submit([
            '0' => 'dropoff.address',
            '1' => 'pickup.timeslot',
            '2' => 'dropoff.address',
            '3' => 'dropoff.timeslot',
            '4' => '',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertCount(2, $form->getErrors());
    }
}
