<?php

namespace AppBundle\DependencyInjection\Security\Factory;

use AppBundle\Security\BearerTokenAuthenticator;
use League\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

class TokenBearerFactory implements SecurityFactoryInterface, AuthenticatorFactoryInterface
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
        return 'bearer_token';
    }

    public function addConfiguration(NodeDefinition $node)
    {
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        // OAuth
        $oauth2AuthenticatorId = 'security.authenticator.bearer_token.oauth2.'.$firewallName;
        $container
            ->setDefinition($oauth2AuthenticatorId, new ChildDefinition(OAuth2Authenticator::class))
            ->replaceArgument('$userProvider', new Reference($userProviderId))
        ;

        // JWT
        $jwtAuthenticatorId = 'security.authenticator.bearer_token.jwt.'.$firewallName;
        $container
            ->setDefinition($jwtAuthenticatorId, new ChildDefinition('lexik_jwt_authentication.security.jwt_authenticator'))
            ->replaceArgument('$userProvider', new Reference($userProviderId))
        ;

        $authenticatorId = 'security.authenticator.bearer_token.'.$firewallName;
        $container
            ->setDefinition($authenticatorId, new ChildDefinition(BearerTokenAuthenticator::class))
            ->replaceArgument('$jwtAuthenticator', new Reference($jwtAuthenticatorId))
            ->replaceArgument('$oauth2Authenticator', new Reference($oauth2AuthenticatorId))
            ->replaceArgument('$firewallName', $firewallName)
        ;

        return $authenticatorId;
    }
}
