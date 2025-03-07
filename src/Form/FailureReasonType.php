<?php

namespace AppBundle\Form;

use AppBundle\Entity\Delivery\FailureReason;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FailureReasonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('code', TextType::class, [
                'required' => true,
                'label' => 'form.failure_reason.code.label'
            ])
            ->add('description', TextType::class, [
                'required' => true,
                'label' => 'form.failure_reason.description.label'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => FailureReason::class,
        ));
    }
}
