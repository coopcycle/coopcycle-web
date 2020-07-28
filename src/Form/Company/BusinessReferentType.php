<?php
declare(strict_types=1);

namespace AppBundle\Form\Company;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BusinessReferentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('familyName', TextType::class, [
                'label' => 'profile.familyName',
            ])
            ->add('givenName', TextType::class, [
                'label' => 'profile.givenName',
            ])
            ->add('mail', EmailType::class, [
                'label' => 'profile.email',
            ])
            ->add('function', TextType::class, [
                'label' => 'form.address.company.function.label',
            ])
        ;

    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'mapped' => false,
        ]);
    }
}
