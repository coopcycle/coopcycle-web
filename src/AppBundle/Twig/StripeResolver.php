<?php

namespace AppBundle\Twig;

use AppBundle\Entity\Restaurant;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class StripeResolver
{
    private $settingsManager;
    private $router;
    private $tokenStorage;

    public function __construct(
        SettingsManager $settingsManager,
        RouterInterface $router,
        TokenStorageInterface $tokenStorage)
    {
        $this->settingsManager = $settingsManager;
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
    }

    public function isLivemode()
    {
        return $this->settingsManager->isStripeLivemode();
    }

    public function canEnableTestmode()
    {
        return $this->settingsManager->canEnableStripeTestmode();
    }

    public function canEnableLivemode()
    {
        return $this->settingsManager->canEnableStripeLivemode();
    }

    public function getOAuthLink(Restaurant $restaurant)
    {
        $redirectUri = $this->router->generate(
            'stripe_connect_standard_account',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $user = $this->getUser();

        // @see https://stripe.com/docs/connect/oauth-reference
        $prefillingData = [
            'stripe_user[email]' => $user->getEmail(),
            'stripe_user[url]' => $restaurant->getWebsite(),
            // TODO : set this after https://github.com/coopcycle/coopcycle-web/issues/234 is solved
            // 'stripe_user[country]' => $restaurant->getAddress()->getCountry(),
            'stripe_user[phone_number]' => $restaurant->getTelephone(),
            'stripe_user[business_name]' => $restaurant->getLegalName(),
            'stripe_user[business_type]' => 'Restaurant',
            'stripe_user[first_name]' => $user->getGivenName(),
            'stripe_user[last_name]' => $user->getFamilyName(),
            'stripe_user[street_address]' => $restaurant->getAddress()->getStreetAddress(),
            'stripe_user[city]' => $restaurant->getAddress()->getAddressLocality(),
            'stripe_user[zip]' => $restaurant->getAddress()->getPostalCode(),
            'stripe_user[physical_product]' => 'Food',
            'stripe_user[shipping_days]' => 1,
            'stripe_user[product_category]' => 'Food',
            'stripe_user[product_description]' => 'Food',
            'stripe_user[currency]' => 'EUR'
        ];

        // @see https://stripe.com/docs/connect/standard-accounts#integrating-oauth
        $queryString = http_build_query(array_merge(
            $prefillingData,
            [
                'response_type' => 'code',
                'client_id' => $this->settingsManager->get('stripe_connect_client_id'),
                'scope' => 'read_write',
                'redirect_uri' => $redirectUri,
                'state' => $restaurant->getId(),
            ]
        ));

        return 'https://connect.stripe.com/oauth/authorize?' . $queryString;
    }

    private function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }
}
