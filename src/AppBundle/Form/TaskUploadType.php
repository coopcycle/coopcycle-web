<?php

namespace AppBundle\Form;

use AppBundle\Utils\TaskSpreadsheetParser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class TaskUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FileType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.task_upload.file',
                'constraints' => [
                    new Assert\File([
                        'mimeTypes' => TaskSpreadsheetParser::getMimeTypes()
                    ])
                ],
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => \stdClass::class,
            'date' => new \DateTime()
        ));
    }
}
