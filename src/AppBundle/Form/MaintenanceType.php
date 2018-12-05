<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class MaintenanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'form.maintenance.message.label',
                'required' => false,
            ])
            ->add('enable', SubmitType::class, [
                'label' => 'form.maintenance.enable.label'
            ])
            ->add('disable', SubmitType::class, [
                'label' => 'form.maintenance.disable.label'
            ]);
    }
}
