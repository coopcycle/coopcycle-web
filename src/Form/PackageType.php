<?php

namespace AppBundle\Form;

use AppBundle\Entity\Package;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
                'required' => false,
            ])
            ->add('color', TextType::class, [
                'label' => 'form.package.color.label',
                'empty_data' => '#03fcdf',
                'required' => false
            ])
            ->add('averageVolumeUnits', IntegerType::class, [
                'label' => 'form.package.average_volume_units.label',
                'help' => 'form.package.average_estimation',
                'required' => false,
            ])
            ->add('maxVolumeUnits', IntegerType::class, [
                'label' => 'form.package.max_volume_units.label',
                'help' => 'form.package.max_volume_units',
                'attr' => [
                    'min' => 1
                ]
            ])
            ->add('averageWeight', NumberType::class, [
                'label' => 'form.package.average_weight.label',
                'help' => 'form.package.average_estimation',
                'scale' => 2,
                'required' => false,
            ])
            ->add('maxWeight', NumberType::class, [
                'label' => 'form.package.max_weight.label',
                'scale' => 2,
                'attr' => [
                    'min' => 1
                ],
                'help' => 'form.package.maxWeight.help'
            ])
            ->add('shortCode', TextType::class, [
                'label' => 'form.package.shortCode.label',
                'required' => false
            ])
            ->add(
                'tags',
                TagsType::class, [
                    'label' => 'form.package.tags.label',
                ]
            );

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            $package = $event->getData();

            if (!is_null($package)) {
                $package->setMaxWeight($package->getMaxWeight() / 1000);
                $package->setAverageWeight($package->getAverageWeight() / 1000);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $package = $event->getForm()->getData();

            $shortCode = $package->getShortCode();
            if (is_null($shortCode)) {
                $package->setShortCode(strtoupper(substr($package->getName(), 0 ,2)));
            }

            $averageWeight = $package->getAverageWeight();
            if (is_null($averageWeight) || $averageWeight === 0) {
                $package->setAverageWeight(0.75 * $package->getMaxWeight()); // Estimated to 75% of max if not set* 0.75); // Estimated to 75% of max if not set
            }

            // store as g
            $package->setAverageWeight(round($package->getAverageWeight()*1000));
            $package->setMaxWeight(round($package->getMaxWeight()*1000));

            $averageVolumeUnits = $package->getAverageVolumeUnits();
            if (is_null($averageVolumeUnits) || $averageVolumeUnits === 0) {
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
