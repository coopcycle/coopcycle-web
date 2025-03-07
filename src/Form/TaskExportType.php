<?php

namespace AppBundle\Form;

use AppBundle\Message\ExportTasks;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class TaskExportType extends AbstractType
{

    public function __construct(
        private MessageBusInterface $messageBus
    )
    { }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('start', DateType::class, [
                'label' => 'form.task_export.start.label',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'html5' => false,
            ])
            ->add('end', DateType::class, [
                'label' => 'form.task_export.end.label',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'html5' => false,
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $taskExport = $event->getForm()->getData();
            $data = $event->getData();

            $envelope = $this->messageBus->dispatch(new ExportTasks(
                new \DateTime($data['start']),
                new \DateTime($data['end'])
            ));


            /** @var HandledStamp $handledStamp */
            $handledStamp = $envelope->last(HandledStamp::class);
            $taskExport->csv = $handledStamp->getResult();

            $event->getForm()->setData($taskExport);

        });
    }


}
