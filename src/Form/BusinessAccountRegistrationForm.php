<?php

namespace AppBundle\Form;

use Nucleos\ProfileBundle\Form\Type\RegistrationFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BusinessAccountRegistrationForm extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        switch ($options['flow_step']) {
            case 1:
                $builder->add('user', RegistrationFormType::class, [
                    'label' => false
                ]);
                break;
            case 2:
                $builder->add('businessAccount', BusinessAccountType::class, [
                    'label' => false
                ]);
				break;
        }
    }

    public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults([
			'data_class' => BusinessAccountRegistration::class,
		]);
	}

    public function getBlockPrefix() : string {
		return 'businessAccountRegistration';
	}
}
