<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Entity\LocalBusiness\DayOfWeekAddress;
use AppBundle\Form\AddressType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DayOfWeekAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('daysOfWeek', TextType::class, [
            ])
            ->add('address', AddressType::class, [
                'street_address_label' => 'localBusiness.form.business_address.label',
                'with_widget' => true,
                'with_description' => false,
                'label' => false,
                'help' => 'localBusiness.form.business_address.help',
                'required' => false,
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
