<?php

namespace AppBundle\DependencyInjection\Security\Factory;

use AppBundle\Security\Authentication\Provider\CartSessionProvider;
use AppBundle\Security\Firewall\CartSessionListener;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\SecurityFactoryInterface;

class CartSessionFactory implements SecurityFactoryInterface
{
    public function create(ContainerBuilder $container, $id, $config, $userProvider, $defaultEntryPoint)
    {
        $providerId = 'security.authentication.provider.cart_session.'.$id;
        $container
            ->setDefinition($providerId, new ChildDefinition(CartSessionProvider::class))
            ->replaceArgument('$userProvider', new Reference($userProvider))
            ->replaceArgument('$providerKey', $id)
        ;

        $listenerId = 'security.authentication.listener.cart_session.'.$id;
        $container->setDefinition($listenerId, new ChildDefinition(CartSessionListener::class));

        return [$providerId, $listenerId, $defaultEntryPoint];
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
}
