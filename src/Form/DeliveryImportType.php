<?php

namespace AppBundle\Form;

use AppBundle\Entity\Store;
use AppBundle\Validator\Constraints\Spreadsheet as AssertSpreadsheet;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DeliveryImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FileType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.delivery_import.file.label',
                'constraints' => [
                    new AssertSpreadsheet('delivery'),
                ],
            ));

        if ($options['with_store']) {
            $builder->add('store', EntityType::class, [
                'label' => 'form.delivery_import.store.label',
                'mapped' => false,
                'class' => Store::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('store')
                        ->orderBy('store.name', 'ASC');
                },
                'choice_label' => 'name',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'with_store' => false,
        ));
    }
}
