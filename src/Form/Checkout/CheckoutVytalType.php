<?php

namespace AppBundle\Form\Checkout;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class CheckoutVytalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'form.checkout_address.reusable_packaging_vytal_code.label',
                'mapped' => false,
                'required' => false,
            ]);
    }
}
