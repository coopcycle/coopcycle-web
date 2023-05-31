<?php

namespace AppBundle\Sylius\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Sylius\Customer\CustomerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Parse Symfony request in the search of `X-Player-Token`.
 * If found, parse it and provides few usefull functions
 */
final class OrderInvitationContext
{
	public function __construct(
        private RequestStack $requestStack,
        private JWSProviderInterface $JWSProvider,
        private IriConverterInterface $iriConverter)
    {}

    /**
     * Check if the valid provided player token is matching with the order in parameter
     * @param OrderInterface $order
     * @return bool
     */
    public function isPlayerOf(OrderInterface $order): bool
    {
        $payload = $this->getPayload();
        if (is_null($payload)) {
            return false;
        }

        $iriOrder = $this->iriConverter->getIriFromItem($order);
        return $iriOrder === $payload['order'];

    }

    /**
     * Get `Customer` from player token
     * @return CustomerInterface|null
     */
    public function getCustomer(): ?CustomerInterface
    {
        $payload = $this->getPayload();
        if (is_null($payload)) {
            return null;
        }

        return $this->iriConverter->getItemFromIri($payload['player']);
    }

    /**
     * @return array|null
     */
    private function getPayload(): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        $token = null;
        if (!is_null($request) && $request->headers->has('X-Player-Token')) {
            $token = $this->requestStack->getCurrentRequest()->headers->get('X-Player-Token');
        }

        if (empty($token)) {
            return null;
        }

        $JWSToken = $this->JWSProvider->load($token);
        if ($JWSToken->isExpired() || $JWSToken->isInvalid()) {
            return null;
        }

        return $JWSToken->getPayload();
    }
}
