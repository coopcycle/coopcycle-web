<?php

namespace AppBundle\Form;

use AppBundle\Entity\Package;
use AppBundle\Entity\Package\PackageWithQuantity;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PackageWithQuantityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('package', EntityType::class, [
                'class' => Package::class,
                'query_builder' => function (EntityRepository $repository) use ($options) {
                    return $repository->createQueryBuilder('p')
                        ->andWhere('p.packageSet = :package_set')
                        ->setParameter('package_set', $options['package_set'])
                        ->orderBy('p.name', 'ASC');
                },
                'label' => 'form.package_with_quantity.package.label',
                'choice_label' => 'name',
                'choice_value' => 'name',
                'placeholder' => 'form.package_with_quantity.package.placeholder',
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'form.package_with_quantity.quantity.label',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PackageWithQuantity::class,
            'package_set' => null
        ));
    }
}
