<?php

namespace AppBundle\Security;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\User;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Sylius\Order\OrderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\Token\JWTPostAuthenticationToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Webmozart\Assert\Assert;

class OrderActionsVoter extends Voter
{
    const EDIT = 'edit';
    const ACCEPT  = 'accept';
    const REFUSE  = 'refuse';
    const DELAY   = 'delay';
    const FULFILL = 'fulfill';
    const CANCEL  = 'cancel';
    const START_PREPARING  = 'start_preparing';
    const FINISH_PREPARING  = 'finish_preparing';
    const RESTORE = 'restore';
    const VIEW    = 'view';
    const VIEW_PUBLIC = 'view_public';

    private static $actions = [
        self::EDIT,
        self::ACCEPT,
        self::REFUSE,
        self::DELAY,
        self::FULFILL,
        self::CANCEL,
        self::START_PREPARING,
        self::FINISH_PREPARING,
        self::RESTORE,
        self::VIEW,
        self::VIEW_PUBLIC,
    ];

    private $authorizationChecker;

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStoreExtractor $tokenExtractor)
    {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenExtractor = $tokenExtractor;
    }

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
        if (self::VIEW_PUBLIC === $attribute) {

            $orderState = $subject->getState();

            $validStates = [
                OrderInterface::STATE_NEW,
                OrderInterface::STATE_ACCEPTED,
                OrderInterface::STATE_FULFILLED,
            ];

            if (!in_array($orderState, $validStates)) {
                return false;
            }

            return true;
        }

        if ($token instanceof OAuth2Token) {

            if (!$this->authorizationChecker->isGranted('ROLE_OAUTH2_ORDERS')) {
                return false;
            }

            if (!$subject->hasVendor()) {
                return false;
            }

            if (self::VIEW === $attribute || self::ACCEPT === $attribute) {

                if ($shop = $this->tokenExtractor->extractShop()) {

                    return $shop === $subject->getRestaurant();
                }
            }

            return false;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return false;
        }

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        Assert::isInstanceOf($user, User::class);

        $ownsRestaurant = $this->isGrantedRestaurant($subject);

        $isCartSessionOwner = false;
        if (($token instanceof JWTUserToken || $token instanceof JWTPostAuthenticationToken) && $token->hasAttribute('cart')) {

            $cart = $token->getAttribute('cart');

            $isCartSessionOwner = $cart && $cart->getId() === $subject->getId();
        }

        $isCustomer = (null !== $subject->getCustomer()
            && $subject->getCustomer()->hasUser()
            && $subject->getCustomer()->getUser() === $user) || $isCartSessionOwner;

        $dispatcher = $this->authorizationChecker->isGranted('ROLE_DISPATCHER');

        if (self::VIEW === $attribute) {
            return $ownsRestaurant || $isCustomer || $dispatcher;
        }

        if (self::EDIT === $attribute) {
            return $isCustomer;
        }

        // For actions like "accept", "refuse", etc...
        return $ownsRestaurant || $dispatcher;
    }

    private function isGrantedRestaurant($subject)
    {
        foreach ($subject->getRestaurants() as $restaurant) {
            if ($this->authorizationChecker->isGranted('edit', $restaurant)) {
                return true;
            }
        }

        return false;
    }
}
