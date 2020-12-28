<?php

namespace AppBundle\Form;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Invitation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InviteUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('email', EmailType::class);

        $builder->add('roles', ChoiceType::class, [
            'mapped' => false,
            'choices' => [
                'roles.ROLE_ADMIN.help' => 'ROLE_ADMIN',
                'roles.ROLE_COURIER.help' => 'ROLE_COURIER',
            ],
            'expanded' => true,
            'multiple' => true,
        ]);
        $builder->add('restaurants', CollectionType::class, array(
            'mapped' => false,
            'entry_type' => EntityType::class,
            'entry_options' => array(
                'label' => 'Restaurants',
                'class' => LocalBusiness::class,
                'choice_label' => 'name',
            ),
            'allow_add' => true,
            'allow_delete' => true,
            'label' => 'profile.managedRestaurants'
        ));
        $builder->add('stores', CollectionType::class, array(
            'mapped' => false,
            'entry_type' => EntityType::class,
            'entry_options' => array(
                'label' => 'Stores',
                'class' => Store::class,
                'choice_label' => 'name',
            ),
            'allow_add' => true,
            'allow_delete' => true,
            'label' => 'profile.managedStores'
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Invitation::class,
        ));
    }
}
