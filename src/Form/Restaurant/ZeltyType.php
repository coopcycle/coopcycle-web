<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Integration\Zelty\ZeltyClient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ZeltyType extends AbstractType
{
    public function __construct(
        private readonly ZeltyClient $zeltyClient,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $webhookBaseUrl = '',
    ) {}

    private function webhookUrl(string $route, array $params = []): string
    {
        $path = $this->urlGenerator->generate($route, $params);

        if ($this->webhookBaseUrl !== '') {
            return rtrim($this->webhookBaseUrl, '/') . $path;
        }

        return $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('zeltyApiKey', PasswordType::class, [
            'always_empty' => false,
            'label' => 'restaurant.form.zelty_api_key',
            'required' => false,
            'mapped' => false,
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $restaurant = $event->getForm()->getParent()->getData();
            $event->getForm()->get('zeltyApiKey')->setData($restaurant?->getZeltyApiKey());
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            if (!$form->isValid()) {
                return;
            }

            $restaurant = $form->getParent()->getData();
            $newApiKey = $form->get('zeltyApiKey')->getData();

            if (empty($newApiKey)) {
                return;
            }

            $originalApiKey = $restaurant->getZeltyApiKey();
            $restaurant->setZeltyApiKey($newApiKey);

            if ($newApiKey !== $originalApiKey) {
                $this->zeltyClient->setAuth($newApiKey);

                $webhooks = [
                    'catalog.push'                     => $this->webhookUrl('_api_/zelty/webhook/catalog/{restaurantId}_post', ['restaurantId' => $restaurant->getId()]),
                    'dish.update'                      => $this->webhookUrl('_api_/zelty/webhook/dish.update_post'),
                    'dish.delete'                      => $this->webhookUrl('_api_/zelty/webhook/dish.delete_post'),
                    'dish.availability_update'         => $this->webhookUrl('_api_/zelty/webhook/dish.availability_update_post'),
                    'menu.update'                      => $this->webhookUrl('_api_/zelty/webhook/menu.update_post'),
                    'menu.delete'                      => $this->webhookUrl('_api_/zelty/webhook/menu.delete_post'),
                    'menu.availability_update'         => $this->webhookUrl('_api_/zelty/webhook/menu.availability_update_post'),
                    'option.update'                    => $this->webhookUrl('_api_/zelty/webhook/option.update_post'),
                    'option_value.availability_update' => $this->webhookUrl('_api_/zelty/webhook/option_value.availability_update_post'),
                    'order.status.update'              => $this->webhookUrl('_api_/zelty/webhook/order.status.update_post'),
                ];

                $secretKey = $this->zeltyClient->upsertWebhooks($webhooks);
                $restaurant->setZeltyWebhookSecretKey($secretKey);
            }
        });
    }
}
