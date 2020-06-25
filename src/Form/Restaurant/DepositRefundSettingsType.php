<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DepositRefundSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('depositRefundOptin', CheckboxType::class, [
                'label' => 'form.deposit_refund_settings.deposit_refund_optin.label',
                'help' => 'form.deposit_refund_settings.deposit_refund_optin.help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => LocalBusiness::class,
        ));
    }
}
