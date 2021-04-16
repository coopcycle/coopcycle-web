<?php

namespace AppBundle\Form;

use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Service\TaskManager;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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
    private $country;

    public function __construct(string $country)
    {
        $this->country = $country;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $addressBookOptions = [
            'label' => $options['street_address_label'],
            'with_addresses' => $options['with_addresses'],
            'with_remember_address' => $options['with_remember_address'],
        ];

        if (isset($options['address_placeholder']) && !empty($options['address_placeholder'])) {
            $addressBookOptions['new_address_placeholder'] = $options['address_placeholder'];
        }

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
            ->add('address', AddressBookType::class, $addressBookOptions)
            ->add('comments', TextareaType::class, [
                'label' => 'form.task.comments.label',
                'required' => false,
                'attr' => ['rows' => '2', 'placeholder' => 'form.task.comments.placeholder']
            ])
            ->add('doneAfter', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd HH:mm:ss',
                'required' => false,
                'html5' => false,
            ])
            ->add('doneBefore', DateType::class, [
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd HH:mm:ss',
                'required' => true,
                'html5' => false,
            ]);

        if ($options['with_tags']) {
            $builder->add('tagsAsString', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Tags'
            ]);
        }

        if ($options['with_recipient_details']) {
            $builder
                ->add('telephone', PhoneNumberType::class, [
                    'label' => 'form.task.telephone.label',
                    'mapped' => false,
                    'format' => PhoneNumberFormat::NATIONAL,
                    'default_region' => strtoupper($this->country),
                ])
                ->add('recipient', TextType::class, [
                    'label' => 'form.task.recipient.label',
                    'help' => 'form.task.recipient.help',
                    'mapped' => false,
                ]);
        }

        if ($options['with_doorstep']) {
            $builder
                ->add('doorstep', CheckboxType::class, [
                    'label' => 'form.task.dropoff.doorstep.label',
                    'required' => false,
                ]);
        }

        if ($builder->has('tagsAsString')) {
            $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

                $form = $event->getForm();
                $task = $event->getData();

                $form->get('tagsAsString')->setData(implode(' ', $task->getTags()));
            });

            $builder->get('tagsAsString')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

                $task = $event->getForm()->getParent()->getData();

                $tagsAsString = $event->getData();
                $tags = explode(' ', $tagsAsString);

                $task->setTags($tags);
            });
        }

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $task = $event->getData();

            if ($form->has('timeSlot') && !$form->get('timeSlot')->isDisabled()) {
                $choice = $form->get('timeSlot')->getData();
                if ($choice) {
                    $choice->applyToTask($task);
                }
            }

            if ($form->has('telephone')) {
                $task->getAddress()->setTelephone($form->get('telephone')->getData());
            }

            if ($form->has('recipient')) {
                $task->getAddress()->setContactName($form->get('recipient')->getData());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Task::class,
            'can_edit_type' => true,
            'with_tags' => true,
            'with_addresses' => [],
            'address_placeholder' => null,
            'with_recipient_details' => false,
            'with_doorstep' => false,
            'street_address_label' => 'form.task.address.label',
            'with_remember_address' => false,
        ));
    }
}
