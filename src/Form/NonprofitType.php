<?php

namespace AppBundle\Form;

use AppBundle\Entity\Nonprofit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class NonprofitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'basics.name'])
            ->add('url', UrlType::class, ['label' => 'localBusiness.form.website', 'required' => false])
            ->add('enabled', CheckboxType::class, [
                'label' => 'basics.enabled',
                'required' => false,
            ])
            ->add('description', TextareaType::class, ['label' => 'basics.description'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Nonprofit::class,
        ));
    }
}
