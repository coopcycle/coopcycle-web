<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Entity\LocalBusiness\DayOfWeekAddress;
use AppBundle\Form\AddressType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DayOfWeekAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('daysOfWeek', TextType::class, [
                'label' => 'form.day_of_week_address.days_of_week.label',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('address', AddressType::class, [
                'street_address_label' => 'form.address.streetAddress.label',
                'with_widget' => true,
                'with_description' => false,
                'label' => false,
                'required' => true,
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => DayOfWeekAddress::class,
        ));
    }
}
