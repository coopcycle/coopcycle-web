<?php

namespace AppBundle\Security;

use AppBundle\Entity\User;
use AppBundle\Entity\Sylius\Order;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\Token\JWTPostAuthenticationToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

class CartSessionVoter extends Voter
{
    const SESSION = 'session';

    private static $actions = [
        self::SESSION,
    ];

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        if (!$subject instanceof Order) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        // (object.getCustomer() != null and object.getCustomer().hasUser() and object.getCustomer().getUser() == user) or (cart_session.cart != null and cart_session.cart.getId() == object.getId())

        if (($token instanceof JWTUserToken || $token instanceof JWTPostAuthenticationToken) && $token->hasAttribute('cart')) {

            $cart = $token->getAttribute('cart');

            if ($cart && $cart->getId() === $subject->getId()) {
                return true;
            }
        }

        if (null === $subject->getCustomer()) {
            return false;
        }

        if (!is_object($user = $token->getUser())) {
            return false;
        }

        return $subject->getCustomer()->hasUser() && $subject->getCustomer()->getUser() === $user;
    }
}
