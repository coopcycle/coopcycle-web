<?php

namespace AppBundle\Form;

use Redis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class MaintenanceType extends AbstractType
{
    private $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'form.maintenance.message.label',
                'required' => false,
                'help' => 'mardown_formatting.help'
            ])
            ->add('enable', SubmitType::class, [
                'label' => 'form.maintenance.enable.label'
            ])
            ->add('disable', SubmitType::class, [
                'label' => 'form.maintenance.disable.label'
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();

            $maintenance = $this->redis->get('maintenance');
            $maintenanceMessage = $this->redis->get('maintenance_message');

            if (!empty($maintenance) && !empty($maintenanceMessage)) {
                $form->get('message')->setData($maintenanceMessage);
            }
        });
    }
}
