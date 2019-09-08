<?php

namespace AppBundle\Form;

use AppBundle\Utils\TaskSpreadsheetParser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TaskUploadType extends AbstractType
{
    private $spreadsheetParser;
    private $validator;

    public function __construct(
        TaskSpreadsheetParser $spreadsheetParser,
        ValidatorInterface $validator)
    {
        $this->spreadsheetParser = $spreadsheetParser;
        $this->validator = $validator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FormType\FileType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.task_upload.file'
            ));

        $builder->get('file')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {

            $file = $event->getData();

            try {
                $tasks = $this->spreadsheetParser->parse($file->getPathname(), $options['date']);

                foreach ($tasks as $task) {
                    $violations = $this->validator->validate($task);
                    if (count($violations) > 0) {
                        throw new \Exception($violations->get(0)->getMessage());
                    }
                }

            } catch (\Exception $e) {
                $event->getForm()->addError(new FormError($e->getMessage()));
                return;
            }

            $taskImport = $event->getForm()->getParent()->getData();
            $taskImport->tasks = $tasks;

            $event->getForm()->getParent()->setData($taskImport);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => \stdClass::class,
            'date' => new \DateTime()
        ));
    }
}
