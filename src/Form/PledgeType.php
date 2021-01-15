<?php

namespace AppBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\AbstractType;
use AppBundle\Form\AddressType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class PledgeType extends AbstractType {

    public function buildForm(FormBuilderInterface $builder, array $options) {

        $builder
            ->add('name', TextType::class, [
                'label' => 'form.suggest.name.label',
                'help' => 'form.suggest.name.help'
            ])
            ->add('address', AddressType::class, [
                'label' => false,
                'with_widget' => true,
                'with_description' => false,
            ]);
    }
}
