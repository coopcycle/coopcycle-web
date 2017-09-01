<?php

namespace AppBundle\Form;

use AppBundle\Entity\ApiUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UpdateProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('username', TextType::class)
                ->add('familyName', TextType::class, array('label' => 'Family name'))
                ->add('givenName', TextType::class, array('label' => 'Given name'))
                ->add('telephone', TextType::class, array('label' => 'Telephone'));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
           'data_class' => ApiUser::class
        ));
    }

    public function getBlockPrefix()
    {
        return 'app_bundle_update_profile_type';
    }
}
