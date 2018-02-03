<?php

namespace AppBundle\Form;

use AppBundle\Entity\Task;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskType extends LocalBusinessType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Dropoff' => Task::TYPE_DROPOFF,
                    'Pickup' => Task::TYPE_PICKUP,
                ],
                'expanded' => true,
                'multiple' => false,
                'disabled' => !$options['can_edit_type']
            ])
            ->add('address', AddressType::class)
            ->add('doneAfter', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd HH:mm'
            ])
            ->add('doneBefore', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd HH:mm'
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Task::class,
            'can_edit_type' => true
        ));
    }
}
