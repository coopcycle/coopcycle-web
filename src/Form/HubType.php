<?php

namespace AppBundle\Form;

use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Restaurant\ShippingOptionsTrait;
use AppBundle\Form\Restaurant\FulfillmentMethodType;
use AppBundle\Form\Restaurant\FulfillmentMethodsTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class HubType extends AbstractType
{
    use ShippingOptionsTrait, FulfillmentMethodsTrait {
        ShippingOptionsTrait::buildForm as buildShippingOptionsForm;
        FulfillmentMethodsTrait::buildForm as buildFulfillmentMethodsForm;
    }

    protected $authorizationChecker;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->buildShippingOptionsForm($builder, $options);
        $this->buildFulfillmentMethodsForm($builder, $options);

        $builder
            ->add('name', TextType::class, ['label' => 'basics.name'])
            ->add('restaurants', CollectionType::class, [
                'entry_type' => EntityType::class,
                'entry_options' => [
                    'label' => false,
                    'class' => LocalBusiness::class,
                    'choice_label' => 'name',
                ],
                'label' => 'form.hub.restaurants.label',
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Hub::class,
        ));
    }
}
