<?php

namespace AppBundle\Form;

use AppBundle\Entity\BusinessAccount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BusinessAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'registration.step.company.name'])
            ->add('address', AddressType::class, [
                'with_widget' => true,
                'with_description' => false,
                'label' => 'registration.company.address',
            ])
            ->add('differentAddressForBilling', CheckboxType::class, [
                'label' => 'registration.company.address.different.for.billing',
                'required' => false,
            ])
            ->add('billingAddress', AddressType::class, [
                'with_widget' => true,
                'with_description' => false,
                'label' => 'registration.company.billing.address',
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $businessAccount = $form->getData();

            if (!$businessAccount->getDifferentAddressForBilling()) {
                $businessAccount->setBillingAddress(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => BusinessAccount::class,
        ));
    }

    public function getBlockPrefix() {
		return 'company';
	}
}
