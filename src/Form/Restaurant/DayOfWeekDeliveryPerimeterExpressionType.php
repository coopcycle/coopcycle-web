<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Entity\LocalBusiness\DayOfWeekDeliveryPerimeterExpression;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DayOfWeekDeliveryPerimeterExpressionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('daysOfWeek', TextType::class, [
                'label' => 'form.day_of_week_address.days_of_week.label',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('expression', HiddenType::class, [
                'label' => 'localBusiness.form.deliveryPerimeterExpression',
                'constraints' => [new Assert\NotBlank()],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DayOfWeekDeliveryPerimeterExpression::class,
        ]);
    }
}
