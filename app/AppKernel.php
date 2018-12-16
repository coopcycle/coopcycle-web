<?php

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Sylius\Bundle\ResourceBundle\SyliusResourceBundle(),
            new Sylius\Bundle\LocaleBundle\SyliusLocaleBundle(),
            new Sylius\Bundle\AttributeBundle\SyliusAttributeBundle(),
            new Sylius\Bundle\TaxationBundle\SyliusTaxationBundle(),
            new Sylius\Bundle\MoneyBundle\SyliusMoneyBundle(),
            new Sylius\Bundle\OrderBundle\SyliusOrderBundle(),
            new Sylius\Bundle\ProductBundle\SyliusProductBundle(),
            new Sylius\Bundle\TaxonomyBundle\SyliusTaxonomyBundle(),
            new Sylius\Bundle\CurrencyBundle\SyliusCurrencyBundle(),
            // Sylius bundles need to be registered before DoctrineBundle
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new Dunglas\ActionBundle\DunglasActionBundle(),
            new ApiPlatform\Core\Bridge\Symfony\Bundle\ApiPlatformBundle(),
            new Nelmio\CorsBundle\NelmioCorsBundle(),
            new FOS\RestBundle\FOSRestBundle(),
            new FOS\UserBundle\FOSUserBundle(),
            new Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle(),
            new Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle(),
            new Snc\RedisBundle\SncRedisBundle(),
            new Knp\Bundle\TimeBundle\KnpTimeBundle(),
            new Cocur\Slugify\Bridge\Symfony\CocurSlugifyBundle(),
            new Vich\UploaderBundle\VichUploaderBundle(),
            new Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle(),
            new Csa\Bundle\GuzzleBundle\CsaGuzzleBundle(),
            new Misd\PhoneNumberBundle\MisdPhoneNumberBundle(),
            new winzou\Bundle\StateMachineBundle\winzouStateMachineBundle(),
            new Knp\Bundle\SnappyBundle\KnpSnappyBundle(),
            new Mailjet\MailjetBundle\MailjetBundle(),
            new M6Web\Bundle\StatsdBundle\M6WebStatsdBundle(),
            new Knp\Bundle\MarkdownBundle\KnpMarkdownBundle(),
            new Craue\ConfigBundle\CraueConfigBundle(),
            new Knp\Bundle\PaginatorBundle\KnpPaginatorBundle(),
            new Sonata\SeoBundle\SonataSeoBundle(),
            new SimpleBus\SymfonyBridge\SimpleBusCommandBusBundle(),
            new SimpleBus\SymfonyBridge\SimpleBusEventBusBundle(),
            new Nelmio\Alice\Bridge\Symfony\NelmioAliceBundle(),
            new Fidry\AliceDataFixtures\Bridge\Symfony\FidryAliceDataFixturesBundle(),
            new Hautelook\AliceBundle\HautelookAliceBundle(),
            new FOS\JsRoutingBundle\FOSJsRoutingBundle(),
            new JMose\CommandSchedulerBundle\JMoseCommandSchedulerBundle(),
            new Nmure\CrawlerDetectBundle\CrawlerDetectBundle(),
            new Symfony\WebpackEncoreBundle\WebpackEncoreBundle(),
            new AppBundle\AppBundle(),
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Symfony\Bundle\WebServerBundle\WebServerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        } else if (in_array($this->getEnvironment(), ['prod'], true)) {
            $bundles[] = new Sentry\SentryBundle\SentryBundle();
        }

        return $bundles;
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return dirname(__DIR__).'/var/cache/'.$this->getEnvironment();
    }

    public function getLogDir()
    {
        return dirname(__DIR__).'/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getRootDir().'/config/config_'.$this->getEnvironment().'.yml');
    }
}
