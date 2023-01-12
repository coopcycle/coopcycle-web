<?php

namespace AppBundle\Form;

use AppBundle\Entity\Warehouse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class WarehouseType extends AbstractType
{
	/*
    protected $authorizationChecker;
    protected $tokenStorage;
    protected $entityManager;
    protected $serializer;
    protected $country;
    protected $debug;
    protected $cashOnDeliveryOptinEnabled;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        GatewayResolver $gatewayResolver,
        string $country,
        bool $debug = false,
        bool $cashOnDeliveryOptinEnabled = false)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->country = $country;
        $this->debug = $debug;
        $this->cashOnDeliveryOptinEnabled = $cashOnDeliveryOptinEnabled;
        $this->gatewayResolver = $gatewayResolver;
    }
    */

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, ['label' => 'localBusiness.form.name'])
            ->add('address', AddressType::class, [
                'with_widget' => true,
                'with_description' => false,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Warehouse::class,
        ));
    }
}
