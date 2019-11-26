<?php

namespace AppBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class InviteUserType extends CreateUserType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->remove('plainPassword');
    }
}
