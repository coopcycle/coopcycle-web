<?php

namespace AppBundle\Form;

use AppBundle\Spreadsheet\DataExporterInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DataExportType extends AbstractType
{
    public function __construct(bool $dv4culEnabled, bool $colisactivEnabled, array $exporters)
    {
        $this->dv4culEnabled = $dv4culEnabled;
        $this->colisactivEnabled = $colisactivEnabled;
        $this->exporters = $exporters;
    }

    private function getExporter(array $data)
    {
        if (isset($data['format']) && !empty($data['format'])) {

            return $this->exporters[$data['format']];
        }

        return $this->exporters['default'];
    }

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

        $formats = [];

        if ($this->dv4culEnabled) {
            $formats['form.task_export.dv4cul.label'] = 'dv4cul';
        }
        if ($this->colisactivEnabled) {
            $formats['form.task_export.colisactiv.label'] = 'colisactiv';
        }

        if (count($formats) > 0) {
            $builder
                ->add('format', ChoiceType::class, [
                    'choices' => $formats,
                    'label' => 'form.task_export.format.label',
                    'required' => false,
                    'expanded' => false,
                    'multiple' => false,
                ]);
        }

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $taskExport = $event->getForm()->getData();

            $data = $event->getData();

            $exporter = $this->getExporter($data);

            $start = new \DateTime($data['start']);
            $start->setTime(0, 0, 0);

            $end = new \DateTime($data['end']);
            $end->setTime(23, 59, 59);

            $content = $exporter->export($start, $end);

            $event->getForm()->setData([
                'content' => $content,
                'content_type' => $exporter->getContentType(),
                'filename' => $exporter->getFilename($start, $end),
            ]);
        });
    }
}
