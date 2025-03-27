<?php

namespace AppBundle;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use AppBundle\DependencyInjection\Security\Factory\TokenBearerFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new TokenBearerFactory());
    }
}
