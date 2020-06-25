<?php

namespace AppBundle\DependencyInjection\Security\Factory;

use AppBundle\Security\Authentication\Provider\TokenBearerProvider;
use AppBundle\Security\Firewall\TokenBearerListener;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

class TokenBearerFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.token_bearer.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition(TokenBearerProvider::class))
            ->replaceArgument('$userProvider', new Reference($userProvider))
            ->replaceArgument('$providerKey', $id)
        ;

        $listenerId = 'security.authentication.listener.token_bearer.'.$id;
        $container->setDefinition($listenerId, new ChildDefinition(TokenBearerListener::class));

        return [$providerId, $listenerId, $defaultEntryPoint];
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
}
