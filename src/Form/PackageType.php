<?php

namespace AppBundle\Form;

use AppBundle\Entity\Package;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.package.name.label'
            ])
            ->add('description', TextType::class, [
                'label' => 'form.package.description.label',
                'empty_data' => '',
                'required' => false
            ])
            ->add('averageVolumeUnits', IntegerType::class, [
                'label' => 'form.package.volume_units.label',
                'help' => 'Estimated to 75% of max if not set',
                'required' => false
            ])
            ->add('maxVolumeUnits', IntegerType::class, [
                'label' => 'form.package.max_volume_units.label',
            ])
            ->add('averageWeight', IntegerType::class, [
                'label' => 'form.package.volume_units.label',
                'help' => 'Estimated to 75% of max if not set',
                'required' => false
            ])
            ->add('maxWeight', IntegerType::class, [
                'label' => 'form.package.max_volume_units.label',
            ])
            ->add('shortCode', TextType::class, [
                'label' => 'form.package.shortCode.label',
                'help' => '2-letters',
                'required' => false
            ])
            ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $package = $event->getData();
            $form = $event->getForm();

            if ($package && null !== $package->getId()) {
                $form->add('name', TextType::class, [
                    'label' => 'form.package.name.label',
                    'help' => $package->getSlug(),
                ]);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($options) {
            $package = $event->getForm()->getData();

            $shortCode = $package->getShortCode();
            if (is_null($shortCode)) {
                $package->setShortCode(strtoupper(substr($package->getName(), 0 ,2)));
            }

            $averageWeight = $package->getAverageWeight();
            if (is_null($averageWeight)) {
                $package->setAverageWeight(round(0.75 * $package->getMaxWeight())); // Estimated to 75% of max if not set* 0.75); // Estimated to 75% of max if not set
            }

            $averageVolumeUnits = $package->getAverageVolumeUnits();
            if (is_null($averageVolumeUnits)) {
                $package->setAverageVolumeUnits(round(0.75 * $package->getMaxVolumeUnits())); // Estimated to 75% of max if not set* 0.75); // Estimated to 75% of max if not set
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Package::class,
        ));
    }
}
