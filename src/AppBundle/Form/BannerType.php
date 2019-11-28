<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class BannerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'form.banner.message.label',
                'help' => 'mardown_formatting.help',
                'required' => false,
            ])
            ->add('enable', SubmitType::class, [
                'label' => 'form.banner.enable.label'
            ])
            ->add('disable', SubmitType::class, [
                'label' => 'form.banner.disable.label'
            ]);
    }
}
