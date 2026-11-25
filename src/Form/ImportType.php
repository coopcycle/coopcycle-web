<?php

namespace AppBundle\Form;

use AppBundle\Entity\ImportQueue;
use AppBundle\Entity\Store;
use AppBundle\Validator\Constraints\Spreadsheet as AssertSpreadsheet;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Uploading a spreadsheet to import.
 *
 * What the rows hold is asked here rather than guessed, as the two kinds want different
 * things of the dispatcher: a delivery goes to a store, a task to a date. The fields of
 * the kind that isn't chosen are hidden, and only its own are validated.
 */
class ImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['with_tasks']) {
            $builder->add('type', ChoiceType::class, [
                'label' => 'form.import.type.label',
                'mapped' => false,
                'expanded' => true,
                'choices' => [
                    'form.import.type.deliveries' => ImportQueue::TYPE_DELIVERIES,
                    'form.import.type.tasks' => ImportQueue::TYPE_TASKS,
                ],
                'data' => ImportQueue::TYPE_DELIVERIES,
                'attr' => ['data-import-kind' => true],
            ]);
        }

        $builder
            ->add('file', FileType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.delivery_import.file.label',
                'attr' => [
                    // The only check before the round-trip, now that the dispatch board's
                    // dropzone and its own list of mime types are gone
                    'accept' => '.csv,.xlsx,.ods',
                ],
                'constraints' => [
                    // Mapped: the columns are matched to their fields on the next screen,
                    // so the file only has to be readable
                    new AssertSpreadsheet('delivery', ['mapped' => true], groups: [ImportQueue::TYPE_DELIVERIES]),
                    new AssertSpreadsheet('task', ['mapped' => true], groups: [ImportQueue::TYPE_TASKS]),
                ],
            ));

        if ($options['with_store']) {
            $builder->add('store', EntityType::class, [
                'label' => 'form.delivery_import.store.label',
                'mapped' => false,
                // Asked for a delivery import only, so the browser can't insist on it
                'required' => false,
                'row_attr' => ['data-import-kind-field' => ImportQueue::TYPE_DELIVERIES],
                'class' => Store::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('store')
                        ->where('store.deletedAt IS NULL')
                        ->orderBy('store.name', 'ASC');
                },
                'choice_label' => 'name',
                'constraints' => [
                    new NotNull(groups: [ImportQueue::TYPE_DELIVERIES]),
                ],
            ]);
        }

        if ($options['with_tasks']) {
            $builder->add('date', DateType::class, [
                'label' => 'form.import.date.label',
                'help' => 'form.import.date.help',
                'mapped' => false,
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'data' => new \DateTime('today'),
                'row_attr' => ['data-import-kind-field' => ImportQueue::TYPE_TASKS],
                'constraints' => [
                    new NotNull(groups: [ImportQueue::TYPE_TASKS]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'with_store' => false,
            'with_tasks' => false,
            // Only the chosen kind's own fields are checked: the other's are not even shown
            'validation_groups' => function (FormInterface $form) {
                return ['Default', self::importType($form)];
            },
        ));
    }

    /**
     * What the submitted form says it is importing, deliveries unless it says otherwise:
     * the screens that import deliveries only don't ask.
     */
    public static function importType(FormInterface $form): string
    {
        if (!$form->has('type')) {
            return ImportQueue::TYPE_DELIVERIES;
        }

        $type = $form->get('type')->getData();

        return ImportQueue::TYPE_TASKS === $type ? ImportQueue::TYPE_TASKS : ImportQueue::TYPE_DELIVERIES;
    }
}
