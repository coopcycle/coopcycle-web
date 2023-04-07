<?php

namespace AppBundle\Sylius\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Sylius\Customer\CustomerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class OrderInvitationContext
{

    private ?array $payload;

	public function __construct(
        RequestStack $requestStack,
        private JWSProviderInterface $JWSProvider,
        private IriConverterInterface $iriConverter)
    {
        $token = $requestStack->getCurrentRequest()->headers->get('X-Player-Token');
        $this->payload = $this->getPayload($token);
    }

    public function isPlayerOf(OrderInterface $order): bool
    {
        if (is_null($this->payload)) {
            return false;
        }
        $iriOrder = $this->iriConverter->getIriFromItem($order);
        return $iriOrder === $this->payload['order'];

    }

    public function getPlayer(): ?CustomerInterface
    {
        if (is_null($this->payload)) {
            return null;
        }

        return $this->iriConverter->getItemFromIri($this->payload['player']);
    }

    /**
     * @param string|null $token
     * @return array|null
     */
    private function getPayload(?string $token): ?array
    {
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
