<?php

namespace AppBundle\Command;

use AppBundle\Entity\Cuisine;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Taxation\TaxesInitializer;
use AppBundle\Sylius\Taxation\TaxesProvider;
use AppBundle\Taxonomy\CuisineProvider;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LogLevel;
use Stripe;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Attribute\AttributeType\TextAttributeType;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Channel\Factory\ChannelFactoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Payment\Model\PaymentMethod;
use Sylius\Component\Promotion\Model\Promotion;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SetupCommand extends Command
{
    private $productRepository;
    private $productManager;
    private $productFactory;

    private $productAttributeRepository;
    private $productAttributeManager;

    private $localeRepository;
    private $localeFactory;

    private $slugify;

    private $locale;

    private $locales = [
        'an',
        'ca',
        'fr',
        'en',
        'es',
        'de',
        'it',
        'pl',
        'pt-BR'
    ];

    private $channels = [
        'web' => 'Web',
        'app' => 'App',
        'pro' => 'Pro'
    ];

    private $onDemandDeliveryProductNames = [
        'an' => 'Entrega baixo demanda',
        'ca' => 'Lliurament a demanda',
        'fr' => 'Livraison à la demande',
        'en' => 'On demand delivery',
        'es' => 'Entrega bajo demanda',
        'de' => 'Lieferung auf Anfrage',
        'it' => 'Consegna su richiesta',
        'pl' => 'Dostawa na żądanie',
        'pt-BR' => 'Entrega sob demanda'
    ];

    private $allergenAttributeNames = [
        'an' => 'Alerchenos',
        'ca' => 'Al·lèrgens',
        'fr' => 'Allergènes',
        'en' => 'Allergens',
        'es' => 'Alérgenos',
        'de' => 'Allergene',
        'it' => 'Allergeni',
        'pl' => 'Alergeny',
        'pt-BR' => 'Alergenos'
    ];

    private $restrictedDietsAttributeNames = [
        'an' => 'Dietas restrinchidas',
        'ca' => 'Dietes restringides',
        'fr' => 'Régimes restreints',
        'en' => 'Restricted diets',
        'es' => 'Dietas restringidas',
        'de' => 'Eingeschränkte Ernährung',
        'it' => 'Piani ristretti',
        'pl' => 'Ograniczone diety',
        'pt-BR' => 'Dietas restritas'
    ];

    private $freeDeliveryPromotionNames = [
        'an' => 'Entrega de baldes',
        'ca' => 'Lliurament gratuït',
        'fr' => 'Livraison offerte',
        'en' => 'Free delivery',
        'es' => 'Entrega gratis',
        'de' => 'Gratisversand',
        'it' => 'Consegna gratuita',
        'pl' => 'Darmowa dostawa',
        'pt-BR' => 'Entrega grátis'
    ];

    private $currencies = [
        'CAD',
        'EUR',
        'GBP',
        'PLN',
        'USD',
        'SEK',
        'BRL',
        'ARS',
        'CRC',
        'AUD',
    ];

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductFactoryInterface $productFactory,
        EntityManagerInterface $productManager,
        RepositoryInterface $productAttributeRepository,
        EntityManagerInterface $productAttributeManager,
        RepositoryInterface $localeRepository,
        FactoryInterface $localeFactory,
        ChannelRepositoryInterface $channelRepository,
        ChannelFactoryInterface $channelFactory,
        RepositoryInterface $currencyRepository,
        FactoryInterface $currencyFactory,
        PromotionRepositoryInterface $promotionRepository,
        FactoryInterface $promotionFactory,
        CuisineProvider $cuisineProvider,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        TaxesProvider $taxesProvider,
        FactoryInterface $taxCategoryFactory,
        ManagerRegistry $doctrine,
        SlugifyInterface $slugify,
        TranslatorInterface $translator,
        SettingsManager $settingsManager,
        UrlGeneratorInterface $urlGenerator,
        string $locale,
        string $country)
    {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productManager = $productManager;

        $this->productAttributeRepository = $productAttributeRepository;
        $this->productAttributeManager = $productAttributeManager;

        $this->localeRepository = $localeRepository;
        $this->localeFactory = $localeFactory;

        $this->channelRepository = $channelRepository;
        $this->channelFactory = $channelFactory;

        $this->currencyRepository = $currencyRepository;
        $this->currencyFactory = $currencyFactory;

        $this->promotionRepository = $promotionRepository;
        $this->promotionFactory = $promotionFactory;

        $this->cuisineProvider = $cuisineProvider;

        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->taxCategoryFactory = $taxCategoryFactory;
        $this->taxesProvider = $taxesProvider;

        $this->paymentMethodRepository =
            $doctrine->getRepository(PaymentMethod::class);

        $this->doctrine = $doctrine;

        $this->slugify = $slugify;

        $this->translator = $translator;

        $this->settingsManager = $settingsManager;

        $this->urlGenerator = $urlGenerator;

        $this->locale = $locale;
        $this->country = $country;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:setup')
            ->setDescription('Setups some basic stuff.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<info>Setting up CoopCycle…</info>');

        $output->writeln('<info>Checking Sylius locales are present…</info>');
        foreach ($this->locales as $locale) {
            $this->createSyliusLocale($locale, $output);
        }

        $output->writeln('<info>Checking Sylius channels are present…</info>');
        foreach ($this->channels as $channelCode => $channelName) {
            $this->createSyliusChannel($channelCode, $channelName, $output);
        }

        $output->writeln('<info>Checking Sylius currencies are present…</info>');
        foreach ($this->currencies as $currencyCode) {
            $this->createSyliusCurrency($currencyCode, $output);
        }

        $output->writeln('<info>Checking Sylius payment methods are present…</info>');
        $this->createSyliusPaymentMethods($output);

        $output->writeln('<info>Checking « on demand delivery » product is present…</info>');
        $this->createOnDemandDeliveryProduct($output);

        $output->writeln('<info>Checking Sylius product attributes are present…</info>');
        $this->createAllergensAttributes($output);
        $this->createRestrictedDietsAttributes($output);

        $output->writeln('<info>Checking Sylius free delivery promotion is present…</info>');
        $this->createFreeDeliveryPromotion($output);

        $output->writeln('<info>Checking cuisines are present…</info>');
        $this->createCuisines($output);

        $output->writeln('<info>Checking Sylius taxes are present…</info>');
        $this->createSyliusTaxes($output);

        $output->writeln('<info>Configuring Stripe webhook endpoint…</info>');
        $this->configureStripeWebhooks($output);

        return 0;
    }

    private function createSyliusLocale($code, OutputInterface $output)
    {
        $locale = $this->localeRepository->findOneByCode($code);

        if (null !== $locale) {
            $output->writeln(sprintf('Sylius locale "%s" already exists', $code));
            return;
        }

        $locale = $this->localeFactory->createNew();
        $locale->setCode($code);

        $this->localeRepository->add($locale);

        $output->writeln(sprintf('Sylius locale "%s" created', $code));
    }

    private function createSyliusChannel($code, $name, OutputInterface $output)
    {
        $channel = $this->channelRepository->findOneByCode($code);

        if (null !== $channel) {
            $output->writeln(sprintf('Sylius channel "%s" already exists', $code));
            return;
        }

        $channel = $this->channelFactory->createNamed($name);
        $channel->setCode($code);

        $this->channelRepository->add($channel);

        $output->writeln(sprintf('Sylius channel "%s" created', $code));
    }

    private function createSyliusCurrency($code, OutputInterface $output)
    {
        $currency = $this->currencyRepository->findOneByCode($code);

        if (null !== $currency) {
            $output->writeln(sprintf('Sylius currency "%s" already exists', $code));
            return;
        }

        $currency = $this->currencyFactory->createNew();
        $currency->setCode($code);

        $this->currencyRepository->add($currency);

        $output->writeln(sprintf('Sylius currency "%s" created', $code));
    }

    private function createSyliusPaymentMethods(OutputInterface $output)
    {
        $methods = [
            [
                'code' => 'CARD',
                'name' => 'Card',
            ],
            [
                'code' => 'GIROPAY',
                'name' => 'Giropay',
                'countries' => ['de'],
            ],
            [
                'code' => 'EDENRED',
                'name' => 'Edenred',
                'countries' => ['fr'],
            ],
            [
                'code' => 'EDENRED+CARD',
                'name' => 'Edenred + Card',
                'countries' => ['fr'],
            ],
        ];

        foreach ($methods as $method) {

            $paymentMethod = $this->paymentMethodRepository->findOneByCode($method['code']);

            if (null === $paymentMethod) {

                $paymentMethod = new PaymentMethod();
                $paymentMethod->setCode($method['code']);
                $paymentMethod->setEnabled(
                    isset($method['countries']) ? in_array($this->country, $method['countries']) : true
                );

                foreach ($this->locales as $locale) {

                    $paymentMethod->setFallbackLocale($locale);
                    $translation = $paymentMethod->getTranslation($locale);

                    $translation->setName($method['name']);
                }

                $this->paymentMethodRepository->add($paymentMethod);
                $output->writeln(sprintf('Creating payment method « %s »', $method['name']));
            } else {
                $output->writeln(sprintf('Payment method « %s » already exists', $method['name']));
            }
        }
    }

    private function createOnDemandDeliveryProduct(OutputInterface $output)
    {
        $product = $this->productRepository->findOneByCode('CPCCL-ODDLVR');

        if (null === $product) {

            $product = $this->productFactory->createNew();
            $product->setCode('CPCCL-ODDLVR');
            $product->setEnabled(true);

            $this->productRepository->add($product);
            $output->writeln('Creating product « on demand delivery »');

        } else {
            $output->writeln('Product « on demand delivery » already exists');
        }

        $output->writeln('Verifying translations for product « on demand delivery »');

        foreach ($this->locales as $locale) {

            $name = $this->onDemandDeliveryProductNames[$locale];

            $product->setFallbackLocale($locale);
            $translation = $product->getTranslation($locale);

            $translation->setName($name);
            $translation->setSlug($this->slugify->slugify($name));
        }

        $this->productManager->flush();
    }

    private function createAllergensAttributes(OutputInterface $output)
    {
        $attribute = $this->productAttributeRepository->findOneByCode('ALLERGENS');

        if (null === $attribute) {

            $attribute = new ProductAttribute();
            $attribute->setCode('ALLERGENS');
            $attribute->setType(TextAttributeType::TYPE);
            $attribute->setStorageType(AttributeValueInterface::STORAGE_JSON);

            $this->productAttributeRepository->add($attribute);
            $output->writeln('Creating attribute « ALLERGENS »');

        } else {
            $output->writeln('Attribute « ALLERGENS » already exists');
        }

        $output->writeln('Verifying translations for attribute « ALLERGENS »');

        foreach ($this->locales as $locale) {

            $attribute->setFallbackLocale($locale);
            $translation = $attribute->getTranslation($locale);

            $translation->setName($this->allergenAttributeNames[$locale]);
        }

        $this->productAttributeManager->flush();
    }

    private function createRestrictedDietsAttributes(OutputInterface $output)
    {
        $attribute = $this->productAttributeRepository->findOneByCode('RESTRICTED_DIETS');

        if (null === $attribute) {

            $attribute = new ProductAttribute();
            $attribute->setCode('RESTRICTED_DIETS');
            $attribute->setType(TextAttributeType::TYPE);
            $attribute->setStorageType(AttributeValueInterface::STORAGE_JSON);

            $this->productAttributeRepository->add($attribute);
            $output->writeln('Creating attribute « RESTRICTED_DIETS »');

        } else {
            $output->writeln('Attribute « RESTRICTED_DIETS » already exists');
        }

        $output->writeln('Verifying translations for attribute « RESTRICTED_DIETS »');

        foreach ($this->locales as $locale) {

            $attribute->setFallbackLocale($locale);
            $translation = $attribute->getTranslation($locale);

            $translation->setName($this->restrictedDietsAttributeNames[$locale]);
        }

        $this->productAttributeManager->flush();
    }

    private function createFreeDeliveryPromotion(OutputInterface $output)
    {
        $promotion = $this->promotionRepository->findOneByCode('FREE_DELIVERY');

        if (null === $promotion) {

            $promotion = $this->promotionFactory->createNew();
            $promotion->setName($this->freeDeliveryPromotionNames[$this->locale]);
            $promotion->setCouponBased(true);
            $promotion->setCode('FREE_DELIVERY');
            $promotion->setPriority(1);

            $promotionAction = new PromotionAction();
            $promotionAction->setType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
            $promotionAction->setConfiguration(['percentage' => 1.0]);

            $promotion->addAction($promotionAction);

            $this->promotionRepository->add($promotion);
            $output->writeln('Creating promotion « FREE_DELIVERY »');

        } else {
            $output->writeln('Promotion « FREE_DELIVERY » already exists');
        }
    }

    private function createCuisines(OutputInterface $output)
    {
        $slugs = $this->cuisineProvider->getSlugs();

        $cuisineRepository = $this->doctrine->getRepository(Cuisine::class);

        $flush = false;
        foreach ($slugs as $slug) {

            $cuisine = $cuisineRepository->findOneByName($slug);

            if (null === $cuisine) {

                $cuisine = new Cuisine();
                $cuisine->setName($slug);

                $this->doctrine->getManagerForClass(Cuisine::class)->persist($cuisine);
                $flush = true;

                $output->writeln(sprintf('Creating cuisine « %s »', $slug));

            } else {
                $output->writeln(sprintf('Cuisine « %s » already exists', $slug));
            }
        }

        if ($flush) {
            $this->doctrine->getManagerForClass(Cuisine::class)->flush();
        }
    }

    private function createSyliusTaxes(OutputInterface $output)
    {
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO   => OutputInterface::VERBOSITY_NORMAL,
        ];

        $taxesInitializer = new TaxesInitializer(
            $this->doctrine->getConnection(),
            $this->taxesProvider,
            $this->taxCategoryRepository,
            $this->doctrine->getManagerForClass(TaxCategory::class),
            new ConsoleLogger($output, $verbosityLevelMap)
        );

        $taxesInitializer->initialize();
    }

    private function configureStripeWebhooks(OutputInterface $output)
    {
        $secretKey = $this->settingsManager->get('stripe_secret_key');

        if (null === $secretKey) {
            $output->writeln('Stripe secret key is not configured, skipping');
            return;
        }

        $stripe = new Stripe\StripeClient([
            'api_key' => $secretKey,
            'stripe_version' => StripeManager::STRIPE_API_VERSION,
        ]);

        $webhookEvents = [
            'account.application.deauthorized',
            'account.updated',
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            // Used for Giropay legacy integration
            'source.chargeable',
            'source.failed',
            'source.canceled',
        ];

        $url = $this->urlGenerator->generate('stripe_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $output->writeln(sprintf('Stripe webhook endpoint url is "%s"', $url));

        // https://stripe.com/docs/api/webhook_endpoints/create?lang=php
        $webhookId = $this->settingsManager->get('stripe_webhook_id');

        if (null !== $webhookId) {

            $output->writeln('Stripe webhook is already configured, updating…');

            $webhookEndpoint = $stripe->webhookEndpoints->retrieve($webhookId);

            $stripe->webhookEndpoints->update($webhookEndpoint->id, [
                'url' => $url,
                'enabled_events' => $webhookEvents,
            ]);

        } else {

            $webhookEndpoint = $stripe->webhookEndpoints->create([
                'url' => $url,
                'enabled_events' => $webhookEvents,
                'connect' => true,
            ]);

            $this->settingsManager->set('stripe_webhook_id', $webhookEndpoint->id);
            $this->settingsManager->set('stripe_webhook_secret', $webhookEndpoint->secret);
            $this->settingsManager->flush();

            $output->writeln('Stripe webhook endpoint created');
        }
    }
}
