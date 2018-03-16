<?php

namespace AppBundle\Form;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use AppBundle\Form\Type\DateRangeType;
use AppBundle\Service\TagManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskType extends AbstractType
{
    private $tagManager;

    public function __construct(TagManager $tagManager)
    {
        $this->tagManager = $tagManager;
    }

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
            ->add('comments', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => '2', 'placeholder' => 'Specify any useful details to complete task']
            ])
            ->add('tagsAsString', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Tags'
            ])
            ->add('save', SubmitType::class);

        if ($options['date_range']) {
            $builder
                ->add('dateRange', DateRangeType::class, [
                    'mapped' => false,
                ]);
        } else {
            $builder
                ->add('doneAfter', DateType::class, [
                    'widget' => 'single_text',
                    'format' => 'yyyy-MM-dd HH:mm:ss',
                    'required' => false
                ])
                ->add('doneBefore', DateType::class, [
                    'widget' => 'single_text',
                    'format' => 'yyyy-MM-dd HH:mm:ss',
                    'required' => false
                ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $task = $event->getData();

            // We are editing an existing task
            if ($task && null !== $task->getId()) {

                // Only non-assigned tasks can be deleted
                if (!$task->isAssigned()) {
                    $form->add('delete', SubmitType::class);
                }

                // Only existing tasks can be assigned
                $assignOptions = array(
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
                );

                if ($task->isAssigned()) {
                    $assignOptions['data'] = $task->getAssignedCourier();
                }

                $form->add('assign', EntityType::class, $assignOptions);

                if ($task->isAssigned()) {
                    $form->get('assign')->setData($task->getAssignedCourier());
                }
            }

        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $task = $event->getData();

            $tags = array_map(function ($tag) {
                return $tag->getSlug();
            }, iterator_to_array($task->getTags()));

            $form->get('tagsAsString')->setData(implode(' ', $tags));

            if ($form->has('dateRange')) {
                $form->get('dateRange')->get('after')->setData($task->getDoneAfter());
                $form->get('dateRange')->get('before')->setData($task->getDoneBefore());
            }
        });

        $builder->get('tagsAsString')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {

            $task = $event->getForm()->getParent()->getData();

            $tagsAsString = $event->getData();
            $slugs = explode(' ', $tagsAsString);
            $tags = $this->tagManager->fromSlugs($slugs);

            $task->setTags($tags);
        });

        if ($builder->has('dateRange')) {
            $builder->get('dateRange')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {

                $data = $event->getData();
                $task = $event->getForm()->getParent()->getData();

                $task->setDoneAfter(new \DateTime($data['after']));
                $task->setDoneBefore(new \DateTime($data['before']));
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Task::class,
            'can_edit_type' => true,
            'date_range' => false,
        ));
    }
}
