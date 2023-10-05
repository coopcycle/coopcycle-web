<?php

namespace AppBundle\Form;

use AppBundle\Entity\Package;
use AppBundle\Entity\Package\PackageWithQuantity;
use Doctrine\ORM\EntityManagerInterface;
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
    protected $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $qb= $this->entityManager->getRepository(Package::class)
                    ->createQueryBuilder('p')
                    ->andWhere('p.packageSet = :package_set')
                    ->setParameter('package_set', $options['package_set'])
                    ->orderBy('p.name', 'ASC');

        $packages = $qb->getQuery()->getResult();

        $builder
            ->add('package', EntityType::class, [
                'class' => Package::class,
                'choices' => $packages,
                'label' => 'form.package_with_quantity.package.label',
                'choice_label' => 'name',
                'choice_value' => 'name',
                'placeholder' => 'form.package_with_quantity.package.placeholder',
                'data' => count($packages) === 1 ? $packages[0] : null,
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
