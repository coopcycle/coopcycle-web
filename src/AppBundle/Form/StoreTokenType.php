<?php

namespace AppBundle\Form;

use AppBundle\Entity\Store;
use AppBundle\Security\StoreTokenManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StoreTokenType extends AbstractType
{
    private $tokenManager;

    public function __construct(StoreTokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $store = $event->getData();

            if (null === $store->getToken()) {
                $form
                    ->add('generate', SubmitType::class, [
                        'label' => 'form.store_token.generate.label'
                    ]);
            } else {
                $form
                    ->add('token', TextType::class, [
                        'label' => 'form.store_token.token.label',
                        'mapped' => false,
                        'data' => $store->getToken()->getToken()
                    ]);
            }
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $store = $event->getData();

            if ($form->getClickedButton() && 'generate' === $form->getClickedButton()->getName()) {
                $jwt = $this->tokenManager->create($store);
                $store->setToken($jwt);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Store::class,
        ));
    }
}
