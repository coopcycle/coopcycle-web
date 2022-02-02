<?php

namespace AppBundle\DependencyInjection\Security\Factory;

use AppBundle\Security\CartSessionAuthenticator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

class CartSessionFactory implements SecurityFactoryInterface, AuthenticatorFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        throw new \LogicException('This method is implemented for BC purpose and should never be called.');
    }

    public function getPosition()
    {
        return 'pre_auth';
    }

    public function getKey()
    {
        return 'cart_session';
    }

    public function addConfiguration(NodeDefinition $node)
    {
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $jwtAuthenticatorId = 'security.authenticator.cart_session.jwt.'.$firewallName;
        $container
            ->setDefinition($jwtAuthenticatorId, new ChildDefinition('lexik_jwt_authentication.security.jwt_authenticator'))
            ->replaceArgument('$userProvider', new Reference($userProviderId))
        ;

        $authenticatorId = 'security.authenticator.cart_session.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition(CartSessionAuthenticator::class))
            ->replaceArgument('$jwtAuthenticator', new Reference($jwtAuthenticatorId))
            ->replaceArgument('$firewallName', $firewallName)
        ;

        return $authenticatorId;
    }
}
