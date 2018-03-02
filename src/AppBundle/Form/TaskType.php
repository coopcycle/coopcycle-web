<?php

namespace AppBundle\Form;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Pickup' => Task::TYPE_PICKUP,
                    'Dropoff' => Task::TYPE_DROPOFF,
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
            ->add('comments', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => '2', 'placeholder' => 'Specify any useful details to complete task']
            ])
            ->add('save', SubmitType::class);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $task = $event->getData();

            $isNewTask = $task->getId() === null;

            // We are editing an existing task
            if (!$isNewTask) {

                // Only non-assigned tasks can be deleted
                if (!$task->isAssigned()) {
                    $form->add('delete', SubmitType::class);
                }

                // Only existing tasks can be assigned
                $form->add('assign', EntityType::class, array(
                    'mapped' => false,
                    'label' => 'Courier',
                    'required' => false,
                    'class' => ApiUser::class,
                    'query_builder' => function (EntityRepository $entityRepository) {
                        return $entityRepository->createQueryBuilder('u')
                            ->where('u.roles LIKE :roles')
                            ->setParameter('roles', '%ROLE_COURIER%')
                            ->orderBy('u.username', 'ASC');
                    },
                    'choice_label' => 'username',
                ));
            }

            if ($task->isAssigned()) {
                $form->get('assign')->setData($task->getAssignedCourier());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Task::class,
            'can_edit_type' => true,
        ));
    }
}
