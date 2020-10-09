<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

class InviteUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('quotesAllowed', CheckboxType::class, [
            'label' => 'form.user.quotes_allowed.label',
            'required' => false
        ]);

        $builder->remove('legal');
        $builder->remove('plainPassword');
    }

    public function getParent()
    {
        return 'AppBundle\Form\RegistrationType';
    }
}
