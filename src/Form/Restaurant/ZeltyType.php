<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Integration\Zelty\ZeltyConnectService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class ZeltyType extends AbstractType
{
    public function __construct(
        private readonly ZeltyConnectService $connectService,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('zeltyApiKey', TextType::class, [
            'label'     => 'restaurant.form.zelty_api_key',
            'help'      => 'restaurant.form.zelty_api_key.help',
            'help_html' => true,
            'required'  => false,
            'mapped'    => false,
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $restaurant = $event->getForm()->getParent()->getData();

            $event->getForm()->get('zeltyApiKey')->setData(
                $restaurant?->getMaskedZeltyApiKey()
            );

            // The dish select is rendered (hidden) even before an API key is set,
            // so the AJAX "connect" flow can reveal it without a page reload.
            if ($restaurant?->getId() !== null) {
                $event->getForm()->add('zeltyDeliveryFeeDishId', HiddenType::class, [
                    'mapped' => false,
                    'attr'   => ['data-restaurant-id' => (string) $restaurant->getId()],
                ]);
                $event->getForm()->get('zeltyDeliveryFeeDishId')
                    ->setData($restaurant->getZeltyDeliveryFeeDishId());
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            if (!$form->isValid()) {
                return;
            }

            $restaurant = $form->getParent()->getData();

            $this->handleApiKey($form, $restaurant);
            $this->handleDeliveryFeeDishId($form, $restaurant);
        });
    }

    private function handleApiKey(\Symfony\Component\Form\FormInterface $form, mixed $restaurant): void
    {
        $newApiKey = $form->get('zeltyApiKey')->getData();

        if (empty($newApiKey)) {
            return;
        }

        // User left the masked placeholder untouched — no change.
        if ($restaurant->hasZeltyApiKey() && $newApiKey === $restaurant->getMaskedZeltyApiKey()) {
            return;
        }

        if ($newApiKey === $restaurant->getZeltyApiKey()) {
            return;
        }

        $this->connectService->connect($restaurant, $newApiKey);
    }

    private function handleDeliveryFeeDishId(\Symfony\Component\Form\FormInterface $form, mixed $restaurant): void
    {
        if (!$form->has('zeltyDeliveryFeeDishId')) {
            return;
        }

        $dishId = $form->get('zeltyDeliveryFeeDishId')->getData();
        $restaurant->setZeltyDeliveryFeeDishId($dishId !== null && $dishId !== '' ? (int) $dishId : null);
    }
}
