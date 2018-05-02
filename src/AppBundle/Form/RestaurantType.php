<?php

namespace AppBundle\Form;

use AppBundle\Entity\Restaurant;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RestaurantType extends LocalBusinessType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $builder->add('contract', ContractType::class);
        }

        $builder->add('deliveryPerimeterExpression', HiddenType::class, ['label' => 'localBusiness.form.deliveryPerimeterExpression',]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => Restaurant::class,
        ));
    }
}
