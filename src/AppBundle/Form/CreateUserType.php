<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class CreateUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('quotesAllowed', CheckboxType::class, [
                'label' => 'form.user.quotes_allowed.label',
                'required' => false
            ])
            ->add('save', SubmitType::class, array('label' => 'basics.save'))
            ->add('sendInvitation', SubmitType::class, array('label' => 'basics.send_invitation'));
    }

    public function getParent()
    {
        return 'AppBundle\Form\RegistrationType';

    }
}
