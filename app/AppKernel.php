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
            new Sylius\Bundle\ResourceBundle\SyliusResourceBundle(),
            new Sylius\Bundle\LocaleBundle\SyliusLocaleBundle(),
            new Sylius\Bundle\AttributeBundle\SyliusAttributeBundle(),
            new Sylius\Bundle\TaxationBundle\SyliusTaxationBundle(),
            new Sylius\Bundle\MoneyBundle\SyliusMoneyBundle(),
            new Sylius\Bundle\OrderBundle\SyliusOrderBundle(),
            new Sylius\Bundle\ProductBundle\SyliusProductBundle(),
            new Sylius\Bundle\TaxonomyBundle\SyliusTaxonomyBundle(),
            new Sylius\Bundle\CurrencyBundle\SyliusCurrencyBundle(),
            new Sylius\Bundle\ChannelBundle\SyliusChannelBundle(),
            new Sylius\Calendar\SyliusCalendarBundle(),
            new Sylius\Bundle\PromotionBundle\SyliusPromotionBundle(),
            new Sylius\Bundle\PaymentBundle\SyliusPaymentBundle(),
            new Sylius\Bundle\CustomerBundle\SyliusCustomerBundle(),
            // Sylius bundles need to be registered before DoctrineBundle
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new ApiPlatform\Core\Bridge\Symfony\Bundle\ApiPlatformBundle(),
            new Nelmio\CorsBundle\NelmioCorsBundle(),
            new FOS\RestBundle\FOSRestBundle(),
            new Nucleos\UserBundle\NucleosUserBundle(),
            new Nucleos\ProfileBundle\NucleosProfileBundle(),
            new Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle(),
            new Gesdinet\JWTRefreshTokenBundle\GesdinetJWTRefreshTokenBundle(),
            new Snc\RedisBundle\SncRedisBundle(),
            new Knp\Bundle\TimeBundle\KnpTimeBundle(),
            new Cocur\Slugify\Bridge\Symfony\CocurSlugifyBundle(),
            new Vich\UploaderBundle\VichUploaderBundle(),
            new Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle(),
            new Misd\PhoneNumberBundle\MisdPhoneNumberBundle(),
            new winzou\Bundle\StateMachineBundle\winzouStateMachineBundle(),
            new Craue\ConfigBundle\CraueConfigBundle(),
            new Knp\Bundle\PaginatorBundle\KnpPaginatorBundle(),
            new Sonata\SeoBundle\SonataSeoBundle(),
            new SimpleBus\SymfonyBridge\SimpleBusCommandBusBundle(),
            new SimpleBus\SymfonyBridge\SimpleBusEventBusBundle(),
            new Nelmio\Alice\Bridge\Symfony\NelmioAliceBundle(),
            new Fidry\AliceDataFixtures\Bridge\Symfony\FidryAliceDataFixturesBundle(),
            new Hautelook\AliceBundle\HautelookAliceBundle(),
            new FOS\JsRoutingBundle\FOSJsRoutingBundle(),
            new Symfony\WebpackEncoreBundle\WebpackEncoreBundle(),
            new Liip\ImagineBundle\LiipImagineBundle(),
            new Oneup\UploaderBundle\OneupUploaderBundle(),
            new League\Bundle\OAuth2ServerBundle\LeagueOAuth2ServerBundle(),
            new NotFloran\MjmlBundle\MjmlBundle(),
            new Sentry\SentryBundle\SentryBundle(),
            new Http\HttplugBundle\HttplugBundle(),
            new HWI\Bundle\OAuthBundle\HWIOAuthBundle(),
            new M6Web\Bundle\DaemonBundle\M6WebDaemonBundle(),
            new Oneup\FlysystemBundle\OneupFlysystemBundle(),
            new Kreait\Firebase\Symfony\Bundle\FirebaseBundle(),
            new ACSEO\TypesenseBundle\ACSEOTypesenseBundle(),
            new AppBundle\AppBundle(),
            new Craue\FormFlowBundle\CraueFormFlowBundle(),
            new Symfony\UX\StimulusBundle\StimulusBundle(),
            new Symfony\UX\React\ReactBundle(),
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
        }

        if ('test' === $this->getEnvironment()) {
            $bundles[] = new FriendsOfBehat\SymfonyExtension\Bundle\FriendsOfBehatSymfonyExtensionBundle();
        }

        return $bundles;
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
    public function getRootDir()
    {
        return $this->getProjectDir().'/app';
    }

    /**
     * Backport of https://github.com/symfony/symfony/pull/37114
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        if (isset($_SERVER['APP_CACHE_DIR'])) {
            return $_SERVER['APP_CACHE_DIR'].'/'.$this->environment;
        }

        return parent::getCacheDir();
    }

    /**
     * Backport of https://github.com/symfony/symfony/pull/37114
     * {@inheritdoc}
     */
    public function getLogDir(): string
    {
        // Just to add the "s"
        return $_SERVER['APP_LOG_DIR'] ?? ($this->getProjectDir().'/var/logs');
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->getProjectDir().'/app/config/config_'.$this->getEnvironment().'.yml');
    }
}
