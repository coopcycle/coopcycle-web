<?php

namespace AppBundle\Form;

use AppBundle\Entity\Package;
use AppBundle\Entity\Package\PackageWithQuantity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
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
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use ($options) {

            $form = $event->getForm();
            $initialData = $event->getData();

            $this->entityManager->getFilters()->enable('soft_deleteable');

            $qb = $this->entityManager->getRepository(Package::class)
                ->createQueryBuilder('p')
                ->where('p.packageSet = :package_set')
                ->setParameter('package_set', $options['package_set'])
                ->orderBy('p.maxVolumeUnits', 'ASC');

            if (null !== $initialData) {
                // This is here to make sure the dropdownn displays something
                // even if the package set does not contain the package
                // This can happen if the configured package set has been changed
                $qb->orWhere('p.id = :package_id');
                $qb->setParameter('package_id', $initialData->getPackage()->getId());
            }

            $data = $qb->getQuery()->getResult();

            $this->entityManager->getFilters()->disable('soft_deleteable');

            $packageFieldOptions = [
                'class' => Package::class,
                'choices' => $data,
                'label' => 'form.package_with_quantity.package.label',
                'choice_label' => 'name',
                'choice_value' => 'name',
                'placeholder' => 'form.package_with_quantity.package.placeholder',
            ];

            // if a new delivery + just 1 choice set default to this choice
            if (is_null($initialData) && count($data) === 1) {
                $packageFieldOptions['data'] = $data[0];
            }

            $form
                ->add('package', EntityType::class, $packageFieldOptions)
                ->add('quantity', IntegerType::class, [
                    'label' => 'form.package_with_quantity.quantity.label',
                ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => PackageWithQuantity::class,
            'package_set' => null
        ));
    }
}
