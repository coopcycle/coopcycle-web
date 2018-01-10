<?php

namespace AppBundle\Form;

use AppBundle\Entity\ClosingRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppearanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('navbarBackgroundColor', TextType::class, [
                'required' => false,
                'label' => 'form.appearance.navbarBackgroundColor.label'
            ])
            ->add('jumbotronBackgroundImage', FileType::class, [
                'required' => false,
                'label' => 'form.appearance.jumbotronBackgroundImage.label'
            ]);

        $builder->get('jumbotronBackgroundImage')
            ->addModelTransformer(new CallbackTransformer(
                function ($filename) use ($options) {
                    if ($filename) {
                        return new File($options['images_dir'].'/'.$filename);
                    }
                },
                function (File $file = null) {
                    if ($file) {
                        return $file->getBasename();
                    }
                }
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => null,
            'images_dir' => ''
        ));
    }
}
