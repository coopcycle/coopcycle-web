<?php

namespace AppBundle\Form;

use AppBundle\Entity\Zone;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GeoJSONUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FormType\FileType::class, array(
                'mapped' => false,
                'required' => true,
            ));

        $builder->get('file')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $file = $event->getData();

            $contents = file_get_contents($file->getPathname());

            // Remove BOM
            // @see https://github.com/emrahgunduz/bom-cleaner/blob/master/bom.php
            if (substr($contents, 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf)) {
                $contents = substr($contents, 3);
            }

            $data = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $event->getForm()->addError(new FormError('The JSON file is not valid'));
                return;
            }

            $geojson = $event->getForm()->getParent()->getData();
            $geojson->features = $data['features'];

            $event->getForm()->getParent()->setData($geojson);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => \stdClass::class,
        ));
    }
}
