<?php

namespace AppBundle\Form\Restaurant;

use AppBundle\Integration\Zelty\ZeltyClient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
        $builder->add('zeltyApiKey', TextType::class, [
            'label'     => 'restaurant.form.zelty_api_key',
            'help'      => 'restaurant.form.zelty_api_key.help',
            'help_html' => true,
            'required'  => false,
            'mapped'    => false,
        ]);

        $builder->add('zeltyWebhookSecretKey', TextType::class, [
            'label'     => 'restaurant.form.zelty_webhook_secret_key',
            'help'      => 'restaurant.form.zelty_webhook_secret_key.help',
            'help_html' => true,
            'required'  => false,
            'mapped'    => false,
        ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $restaurant = $event->getForm()->getParent()->getData();

            $event->getForm()->get('zeltyApiKey')->setData(
                $restaurant?->getMaskedZeltyApiKey()
            );

            $secretKey = $restaurant?->getZeltyWebhookSecretKey();
            $event->getForm()->get('zeltyWebhookSecretKey')->setData(
                $this->maskSecretKey($secretKey)
            );
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            if (!$form->isValid()) {
                return;
            }

            $restaurant = $form->getParent()->getData();

            $this->handleApiKey($form, $restaurant);
            $this->handleWebhookSecretKey($form, $restaurant);
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

        $originalApiKey = $restaurant->getZeltyApiKey();
        $restaurant->setZeltyApiKey($newApiKey);

        if ($newApiKey === $originalApiKey) {
            return;
        }

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

        $returnedSecret = $this->zeltyClient->upsertWebhooks($webhooks);

        // Zelty sometimes returns an obfuscated secret (e.g. "******b286") — only save when it's the real value.
        if (!str_contains($returnedSecret, '*')) {
            $restaurant->setZeltyWebhookSecretKey($returnedSecret);
        }
    }

    private function handleWebhookSecretKey(\Symfony\Component\Form\FormInterface $form, mixed $restaurant): void
    {
        $newSecretKey = $form->get('zeltyWebhookSecretKey')->getData();

        if (empty($newSecretKey)) {
            return;
        }

        // User left the masked placeholder untouched — no change.
        $currentSecret = $restaurant->getZeltyWebhookSecretKey();
        if ($newSecretKey === $this->maskSecretKey($currentSecret)) {
            return;
        }

        $restaurant->setZeltyWebhookSecretKey($newSecretKey);
    }

    private function maskSecretKey(?string $secretKey): ?string
    {
        if ($secretKey === null || $secretKey === '' || str_contains($secretKey, '*')) {
            return null;
        }

        return str_repeat('•', strlen($secretKey));
    }
}
