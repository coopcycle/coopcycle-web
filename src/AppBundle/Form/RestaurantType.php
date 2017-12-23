<?php

namespace AppBundle\Form;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\DeliveryService;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RestaurantType extends LocalBusinessType
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

        $builder
            ->add('deliveryService', DeliveryServiceType::class, ['mapped' => false])
            // ->add('servesCuisine', CollectionType::class, array(
            //     'entry_type' => EntityType::class,
            //     'entry_options' => array(
            //         'label' => 'Cuisine',
            //         'class' => 'AppBundle:Cuisine',
            //         'choice_label' => 'name',
            //         'query_builder' => function (EntityRepository $er) {
            //             return $er->createQueryBuilder('c')->orderBy('c.name', 'ASC');
            //         },
            //     ),
            //     'allow_add' => true,
            //     'allow_delete' => true,
            // ))
            ;

        if ($isAdmin) {
            $builder->add('contract', ContractType::class);
        }

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($options) {

                $restaurant = $event->getForm()->getData();

                $type = $event->getForm()->get('deliveryService')->get('type')->getData();

                $deliveryService = new DeliveryService\Core();

                if ($type === 'applicolis') {
                    $token = $event->getForm()->get('deliveryService')->get('token')->getData();
                    $deliveryService = $restaurant->getDeliveryService();

                    if (null === $deliveryService) {
                        $deliveryService = new DeliveryService\AppliColis();
                    }

                    $deliveryService->setToken($token);
                }

                $restaurant->setDeliveryService($deliveryService);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults(array(
            'data_class' => Restaurant::class,
        ));
    }
}
