<?php

namespace AppBundle\Form;

use AppBundle\Entity\User;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskCompleteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('notes', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'form.task_event.notes.placeholder',
                    'rows' => 2,
                ]
            ])
            ->add('done', SubmitType::class, [
                'label' => 'form.task_complete.done.label'
            ])
            ->add('fail', SubmitType::class, [
                'label' => 'form.task_complete.fail.label'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Task::class,
        ));
    }
}
