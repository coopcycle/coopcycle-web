<?php

namespace AppBundle\Form;

use AppBundle\Entity\Store;
use AppBundle\Entity\Delivery\PricingRuleSet;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use Vich\UploaderBundle\Form\Type\VichImageType;

class StoreType extends LocalBusinessType
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $isAdmin = false;
        if ($token = $this->tokenStorage->getToken()) {
            if ($user = $token->getUser()) {
                $isAdmin = $user->hasRole('ROLE_ADMIN');
            }
        }

        if ($isAdmin) {
            $builder
                ->add('pricingRuleSet', EntityType::class, array(
                    'label' => 'form.store_type.pricing_rule_set.label',
                    'class' => PricingRuleSet::class,
                    'choice_label' => 'name',
                    'query_builder' => function (EntityRepository $er) {
                        return $er->createQueryBuilder('prs')->orderBy('prs.name', 'ASC');
                    }
                ));
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => Store::class,
        ));
    }
}
