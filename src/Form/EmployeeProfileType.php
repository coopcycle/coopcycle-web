<?php

namespace AppBundle\Form;

use AppBundle\Entity\EmployeeProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmployeeProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('contractStartDate', DateType::class, [
                'label' => 'hr.contractStartDate',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'hr.dateOfBirth',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('addressStreet', TextType::class, [
                'label' => 'hr.address.street',
                'required' => false,
            ])
            ->add('addressPostalCode', TextType::class, [
                'label' => 'hr.address.postalCode',
                'required' => false,
            ])
            ->add('addressLocality', TextType::class, [
                'label' => 'hr.address.locality',
                'required' => false,
            ])
            ->add('addressCountry', TextType::class, [
                'label' => 'hr.address.country',
                'required' => false,
            ])
            ->add('salaryType', ChoiceType::class, [
                'label' => 'hr.salaryType',
                'choices' => [
                    'hr.salaryType.hourly' => EmployeeProfile::SALARY_TYPE_HOURLY,
                    'hr.salaryType.monthly' => EmployeeProfile::SALARY_TYPE_MONTHLY,
                ],
                'placeholder' => '',
                'required' => false,
            ])
            ->add('salaryAmount', NumberType::class, [
                'label' => 'hr.salaryAmount',
                'html5' => true,
                'scale' => 2,
                'required' => false,
            ])
            ->add('weeklyContractedHours', NumberType::class, [
                'label' => 'hr.weeklyContractedHours',
                'html5' => true,
                'scale' => 2,
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => EmployeeProfile::class,
        ]);
    }
}
