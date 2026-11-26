<?php

namespace AppBundle\Form;

use AppBundle\Entity\ImportQueue;
use AppBundle\Spreadsheet\Column\ColumnDefinition;
use AppBundle\Spreadsheet\Column\ColumnGroup;
use AppBundle\Validator\Constraints\ColumnMapping as AssertColumnMapping;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Which field each column of an uploaded file holds.
 *
 * One choice per column of the file, keyed by its position: a file can have two columns
 * with the same header, or none at all, and they still have to be told apart. The fields
 * on offer are the ones the parser of that kind of import describes.
 */
class ImportMappingType extends AbstractType
{
    public function __construct(private TranslatorInterface $translator)
    {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = $this->choices($options['columns']);

        foreach ($options['file_columns'] as $index => $header) {
            $builder->add((string) $index, ChoiceType::class, [
                'label' => '' === trim($header)
                    // A column with no header at all, which the user has to recognise by its values
                    ? $this->translator->trans('import.mapping.column.unnamed', ['%number%' => $index + 1])
                    : $header,
                'choices' => $choices,
                'required' => false,
                'placeholder' => 'import.mapping.ignore',
                'attr' => ['data-column-mapping-choice' => $index],
            ]);
        }
    }

    /**
     * Grouped by what the field is about, so that the pickup and the dropoff of the same
     * thing don't sit next to each other in a list of twenty
     *
     * @param ColumnDefinition[] $columns
     * @return array<string,array<string,string>>
     */
    private function choices(array $columns): array
    {
        $choices = [];

        foreach ($columns as $column) {
            $group = $this->translator->trans($column->group->labelKey());

            $choices[$group][$this->translator->trans($column->labelKey())] = $column->key;
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // The data is the mapping itself, a plain array of column index => field
            'data_class' => null,
            'import_type' => ImportQueue::TYPE_DELIVERIES,
        ]);

        // The constraint checks the mapping against the columns of that kind of import
        $resolver->setNormalizer('constraints', fn (Options $options, $value) => [
            new AssertColumnMapping(['type' => $options['import_type']]),
        ]);

        $resolver->setRequired(['columns', 'file_columns']);
        // ColumnDefinition[], and the header row of the file by column index
        $resolver->setAllowedTypes('columns', 'array');
        $resolver->setAllowedTypes('file_columns', 'array');
        $resolver->setAllowedTypes('import_type', 'string');
    }
}
