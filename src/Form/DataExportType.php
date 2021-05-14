<?php

namespace AppBundle\Form;

use AppBundle\Spreadsheet\AbstractDataExporter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DataExportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('start', DateType::class, [
                'label' => 'form.task_export.start.label',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'html5' => false,
                'data' => new \DateTime('now'),
            ])
            ->add('end', DateType::class, [
                'label' => 'form.task_export.end.label',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'html5' => false,
                'data' => new \DateTime('now'),
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {

            $taskExport = $event->getForm()->getData();

            $data = $event->getData();

            $start = new \DateTime($data['start']);
            $start->setTime(0, 0, 0);

            $end = new \DateTime($data['end']);
            $end->setTime(23, 59, 59);

            $csv = $options['data_exporter']->export($start, $end);

            $event->getForm()->setData(['csv' => $csv]);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_exporter' => null,
        ));

        $resolver->setAllowedTypes('data_exporter', [AbstractDataExporter::class]);
    }
}
