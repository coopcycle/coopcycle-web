<?php


namespace AppBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class RestaurantAdminType extends RestaurantType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);
        $builder->add('contract', ContractType::class);

    }

    public function getBlockPrefix() {
        return 'restaurant';
    }

}