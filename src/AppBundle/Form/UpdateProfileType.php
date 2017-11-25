<?php

namespace AppBundle\Form;

use AppBundle\Entity\ApiUser;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UpdateProfileType extends AbstractType
{
    private $countryIso;

    public function __construct($countryIso)
    {
        $this->countryIso = strtoupper($countryIso);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('username', TextType::class)
                ->add('familyName', TextType::class, array('label' => 'Family name'))
                ->add('givenName', TextType::class, array('label' => 'Given name'))
                ->add('telephone', PhoneNumberType::class,
                    array('label' => 'Telephone',
                          'format' => PhoneNumberFormat::NATIONAL,
                          'default_region' => $this->countryIso));
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
