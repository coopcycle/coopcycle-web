<?php

namespace AppBundle\Command;

use AppBundle\Entity\CityZone;
use AppBundle\Entity\Cuisine;
use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Geography\CityZoneImporter;
use AppBundle\Message\CreateWebhookEndpoint;
use AppBundle\MessageHandler\CreateWebhookEndpointHandler;
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

    private $channels = [
        'web' => 'Web',
        'app' => 'App',
        'pro' => 'Pro'
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
        'MXN',
        'DKK',
        'CLP',
        'HUF',
        'COP',
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
        UrlGeneratorInterface $urlGenerator,
        CreateWebhookEndpointHandler $createWebhookEndpointHandler,
        CityZoneImporter $cityZoneImporter,
        string $locale,
        string $country,
        string $localeRegex,
        string $cityZonesUrl,
        string $cityZonesProvider,
        $cityZonesOptions)
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

        $this->urlGenerator = $urlGenerator;

        $this->createWebhookEndpointHandler = $createWebhookEndpointHandler;

        $this->locale = $locale;
        $this->country = $country;
        $this->localeRegex = $localeRegex;

        $this->cityZoneImporter = $cityZoneImporter;
        $this->cityZonesUrl = $cityZonesUrl;
        $this->cityZonesProvider = $cityZonesProvider;
        $this->cityZonesOptions = $cityZonesOptions;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:setup')
            ->setDescription('Setups some basic stuff.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->locales = explode('|', $this->localeRegex);
    }

    /**
     * @return int
     */
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

        if (!empty($this->cityZonesUrl) && !empty($this->cityZonesProvider)) {
            $output->writeln('<info>Configuring city zones…</info>');
            $this->configureCityZones($output);
        }

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
            [
                'code' => 'CASH_ON_DELIVERY',
                'name' => 'Cash on delivery',
                'countries' => ['mx','ar'],
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

            $name = $this->translator->trans('products.on_demand_delivery.name', [], 'messages', $locale);

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

            $name = $this->translator->trans('form.product.allergens.label', [], 'messages', $locale);

            $attribute->setFallbackLocale($locale);
            $translation = $attribute->getTranslation($locale);

            $translation->setName($name);
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

            $name = $this->translator->trans('form.product.restricted_diets.label', [], 'messages', $locale);

            $attribute->setFallbackLocale($locale);
            $translation = $attribute->getTranslation($locale);

            $translation->setName($name);
        }

        $this->productAttributeManager->flush();
    }

    private function createFreeDeliveryPromotion(OutputInterface $output)
    {
        $promotion = $this->promotionRepository->findOneByCode('FREE_DELIVERY');

        if (null === $promotion) {

            $name = $this->translator->trans('promotions.heading.free_delivery', [], 'messages', $this->locale);

            $promotion = $this->promotionFactory->createNew();
            $promotion->setName($name);
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
        $url = $this->urlGenerator->generate('stripe_webhook', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $output->writeln(sprintf('Stripe webhook endpoint url is "%s"', $url));

        foreach (['test', 'live'] as $mode) {
            $message = new CreateWebhookEndpoint($url, $mode);
            call_user_func_array($this->createWebhookEndpointHandler, [ $message ]);
        }
    }

    private function configureCityZones(OutputInterface $output)
    {
        $qb = $this->doctrine->getRepository(CityZone::class)->createQueryBuilder('cz');

        // Useful for debugging
        // $qb->delete()->getQuery()->execute();
        // $this->doctrine->getManagerForClass(CityZone::class)->flush();

        $count = $qb->select('COUNT(cz.id)')->getQuery()->getSingleScalarResult();

        if ($count > 0) {
            $output->writeln('City zones already configured');
            return;
        }

        $cityZones = $this->cityZoneImporter->import(
            $this->cityZonesUrl,
            $this->cityZonesProvider,
            $this->cityZonesOptions
        );

        $output->writeln(sprintf('Found %d city zones', count($cityZones)));

        foreach ($cityZones as $cityZone) {
            $this->doctrine->getManagerForClass(CityZone::class)->persist($cityZone);
        }

        $this->doctrine->getManagerForClass(CityZone::class)->flush();
    }
}
