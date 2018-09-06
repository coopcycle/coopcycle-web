<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class StripeLivemodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('enable', SubmitType::class, [
                'label' => 'form.stripe_livemode.enable.label'
            ])
            ->add('disable', SubmitType::class, [
                'label' => 'form.stripe_livemode.disable.label'
            ])
            ->add('disable_and_enable_maintenance', SubmitType::class, [
                'label' => 'form.stripe_livemode.disable_and_enable_maintenance.label'
            ]);
    }
}
