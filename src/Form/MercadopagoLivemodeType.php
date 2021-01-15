<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class MercadopagoLivemodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('enable', SubmitType::class, [
                'label' => 'form.mercadopago_livemode.enable.label'
            ])
            ->add('disable', SubmitType::class, [
                'label' => 'form.mercadopago_livemode.disable.label'
            ])
            ->add('disable_and_enable_maintenance', SubmitType::class, [
                'label' => 'form.mercadopago_livemode.disable_and_enable_maintenance.label'
            ]);
    }
}
