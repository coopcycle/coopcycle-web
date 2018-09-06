<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class MaintenanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('enable', SubmitType::class, [
                'label' => 'form.maintenace.enable.label'
            ])
            ->add('disable', SubmitType::class, [
                'label' => 'form.maintenace.disable.label'
            ]);
    }
}
