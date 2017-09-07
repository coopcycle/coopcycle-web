<?php

namespace AppBundle\Form;

use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Entity\DeliveryAddress;

class DeliveryAddressType extends AddressType
{

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => DeliveryAddress::class,
        ));
    }
}
