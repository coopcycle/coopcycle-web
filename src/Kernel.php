<?php

namespace AppBundle;

use AppBundle\Transporter\TransporterImporterInterface;
use AppBundle\Transporter\TransporterTransformerInterface;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        // Here rather than with #[AutoconfigureTag] on the interfaces: Symfony only reads
        // it from an interface found while scanning a `resource:` directory, which
        // src/Transporter isn't, and the private integrations' services must get it too.
        $container->registerForAutoconfiguration(TransporterImporterInterface::class)
            ->addTag(TransporterImporterInterface::TAG);
        $container->registerForAutoconfiguration(TransporterTransformerInterface::class)
            ->addTag(TransporterTransformerInterface::TAG);
    }

    /**
     * {@inheritdoc}
     */
    public function getProjectDir(): string
    {
        if (isset($_ENV['APP_PROJECT_DIR'])) {
            return $_ENV['APP_PROJECT_DIR'];
        } elseif (isset($_SERVER['APP_PROJECT_DIR'])) {
            return $_SERVER['APP_PROJECT_DIR'];
        }

        return parent::getProjectDir();
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir(): string
    {
        // Just to add the "s"
        return $_SERVER['APP_LOG_DIR'] ?? ($this->getProjectDir().'/var/logs');
    }
}
