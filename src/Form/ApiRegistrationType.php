<?php

namespace AppBundle\Form;

use AppBundle\Form\Model\ApiRegistration;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ApiRegistrationType extends AbstractType
{
    private $countryIso;

    public function __construct($countryIso)
    {
        $this->countryIso = strtoupper($countryIso);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('email', EmailType::class)
                ->add('username', TextType::class)
                ->add('givenName', TextType::class)
                ->add('familyName', TextType::class)
                ->add('fullName', TextType::class, ['mapped' => false])
                ->add('plainPassword', RepeatedType::class, [
                    'type' => PasswordType::class,
                    'first_name' => 'password',
                    'second_name' => 'password_confirmation',
                    'invalid_message' => 'fos_user.password.mismatch'
                ])
                ->add('telephone', PhoneNumberType::class, [
                    'required' => false,
                    'format' => PhoneNumberFormat::NATIONAL,
                    'default_region' => strtoupper($this->countryIso)
                ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ApiRegistration::class,
            'csrf_protection' => false
        ]);
    }
}
