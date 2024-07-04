<?php

namespace AppBundle\Form\Checkout;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CheckoutTipType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('amount', NumberType::class, [
                'label' => 'form.checkout_address.tip_amount.label',
                'mapped' => false,
                'required' => false,
                'html5' => true,
                'attr'  => array(
                    'min'  => 0,
                    'step' => 0.5,
                ),
                'constraints' => [
                    new Assert\GreaterThanOrEqual(0),
                ],
            ]);
    }
}
