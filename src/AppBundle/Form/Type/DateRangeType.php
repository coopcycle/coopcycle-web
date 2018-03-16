<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;

class DateRangeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('after', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd HH:mm',
                'required' => false
            ])
            ->add('before', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd HH:mm',
                'required' => false
            ]);
    }
}
