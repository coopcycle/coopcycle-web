<?php

namespace AppBundle\Form;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Invitation;
use AppBundle\Validator\Constraints\UserWithSameEmailNotExists as AssertUserWithSameEmailNotExists;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints as Assert;

class InviteUserType extends AbstractType
{
    public function __construct(AuthorizationCheckerInterface $authorizationChecker) {
        $this->asAdmin = $authorizationChecker->isGranted('ROLE_ADMIN');
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('email', EmailType::class, [
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Email([
                    'mode' => Assert\Email::VALIDATION_MODE_STRICT,
                ]),
                new AssertUserWithSameEmailNotExists(),
            ],
        ]);

        $choices = ['roles.ROLE_COURIER.help' => 'ROLE_COURIER',];

        if ($this->asAdmin) {
            array_unshift($choices, ['roles.ROLE_ADMIN.help' => 'ROLE_ADMIN']);
        }

        $builder->add('roles', ChoiceType::class, [
            'mapped' => false,
            'choices' => $choices,
            'expanded' => true,
            'multiple' => true,
        ]);

        if ($this->asAdmin) {
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
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Invitation::class,
        ));
    }
}
