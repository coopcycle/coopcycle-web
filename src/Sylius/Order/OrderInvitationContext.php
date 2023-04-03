<?php

namespace AppBundle\Sylius\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Sylius\Customer\CustomerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class OrderInvitationContext
{
	public function __construct(
        RequestStack $requestStack,
        IriConverterInterface $iriConverter)
    {
        $this->requestStack = $requestStack;
        $this->iriConverter = $iriConverter;
    }

    public function isGuest(): bool
    {
    	$session = $this->requestStack->getSession();

    	return $session->has('guest_customer');
    }

    public function getCustomer(): ?CustomerInterface
    {
    	$session = $this->requestStack->getSession();

    	if ($session->has('guest_customer')) {

    		return $this->iriConverter->getItemFromIri($session->get('guest_customer'));
    	}

    	return null;
    }
}
