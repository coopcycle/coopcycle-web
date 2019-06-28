<?php

namespace AppBundle\Form;

use AppBundle\Entity\TaskEvent;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('notes', TextareaType::class, [
                'help' => 'form.task_event.notes.help',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'form.task_event.notes.placeholder',
                    'rows' => 2,
                ]
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $taskEvent = $event->getData();

            $form->get('notes')->setData($taskEvent->getData('notes'));
        });

        $builder->get('notes')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $taskEvent = $event->getForm()->getParent()->getData();
            $notes = $event->getData();

            $taskEvent->setData('notes', $notes);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => TaskEvent::class,
        ));
    }
}
