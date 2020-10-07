<?php

namespace AppBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class HasCustomerVoter implements VoterInterface
{
    private $cartContext;

    public function __construct(CartContextInterface $cartContext)
    {
        $this->cartContext = $cartContext;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $result = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {

            if (!is_string($attribute)) {
                continue;
            }

            if ('HAS_CUSTOMER' !== $attribute) {
                continue;
            }

            $cart = $this->cartContext->getCart();

            if (null === $cart) {

                return $result;
            }

            $customer = $cart->getCustomer();

            if (null === $customer || null === $customer->getEmail()) {

                return self::ACCESS_DENIED;
            }

            return self::ACCESS_GRANTED;
        }

        return $result;
    }
}
