<?php

namespace AppBundle;

use AppBundle\DependencyInjection\Security\Factory\TokenBearerFactory;
use AppBundle\DependencyInjection\Security\Factory\CartSessionFactory;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AppBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new TokenBearerFactory());
        $extension->addSecurityListenerFactory(new CartSessionFactory());
    }
}
