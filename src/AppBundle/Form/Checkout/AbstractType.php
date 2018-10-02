<?php

namespace AppBundle\Form\Checkout;

use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Form\AbstractType as BaseAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AbstractType extends BaseAbstractType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => OrderInterface::class,
        ));
    }
}
