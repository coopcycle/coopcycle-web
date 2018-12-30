<?php

namespace AppBundle\Command;

use Cocur\Slugify\SlugifyInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use FOS\UserBundle\Model\UserInterface;
use JMose\CommandSchedulerBundle\Entity\ScheduledCommand;
use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Attribute\AttributeType\TextAttributeType;
use Sylius\Component\Attribute\Model\AttributeValueInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SetupCommand extends Command
{
    private $productRepository;
    private $productManager;
    private $productFactory;

    private $productAttributeRepository;
    private $productAttributeManager;

    private $scheduledCommandRepository;
    private $scheduledCommandManager;

    private $localeRepository;
    private $localeFactory;

    private $slugify;
    private $locales = [
        'fr',
        'en',
        'es',
        'de'
    ];

    private $onDemandDeliveryProductNames = [
        'fr' => 'Livraison à la demande',
        'en' => 'On demand delivery',
        'es' => 'Entrega bajo demanda',
        'de' => 'Lieferung auf Anfrage'
    ];

    private $allergenAttributeNames = [
        'fr' => 'Allergènes',
        'en' => 'Allergens',
        'es' => 'Alérgenos',
        'de' => 'Allergene',
    ];

    private $restrictedDietsAttributeNames = [
        'fr' => 'Régimes restreints',
        'en' => 'Restricted diets',
        'es' => 'Dietas restringidas',
        'de' => 'Eingeschränkte Ernährung',
    ];

    private $currencies = [
        'EUR',
        'GBP',
    ];

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductFactoryInterface $productFactory,
        ObjectManager $productManager,
        RepositoryInterface $productAttributeRepository,
        ObjectManager $productAttributeManager,
        RepositoryInterface $localeRepository,
        FactoryInterface $localeFactory,
        RepositoryInterface $currencyRepository,
        FactoryInterface $currencyFactory,
        ManagerRegistry $doctrine,
        SlugifyInterface $slugify)
    {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->productManager = $productManager;

        $this->productAttributeRepository = $productAttributeRepository;
        $this->productAttributeManager = $productAttributeManager;

        $this->scheduledCommandRepository =
            $doctrine->getRepository(ScheduledCommand::class);
        $this->scheduledCommandManager =
            $doctrine->getManagerForClass(ScheduledCommand::class);

        $this->localeRepository = $localeRepository;
        $this->localeFactory = $localeFactory;

        $this->currencyRepository = $currencyRepository;
        $this->currencyFactory = $currencyFactory;

        $this->slugify = $slugify;

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

        $output->writeln('<info>Checking Sylius currencies are present…</info>');
        foreach ($this->currencies as $currencyCode) {
            $this->createSyliusCurrency($currencyCode, $output);
        }

        $output->writeln('<info>Checking « on demand delivery » product is present…</info>');
        $this->createOnDemandDeliveryProduct($output);

        $output->writeln('<info>Checking Sylius product attributes are present…</info>');
        $this->createAllergensAttributes($output);
        $this->createRestrictedDietsAttributes($output);

        $output->writeln('<info>Checking commands are scheduled…</info>');
        $this->createScheduledCommands($output);
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

    private function createScheduledCommands(OutputInterface $output)
    {
        $flushTracking = $this->scheduledCommandRepository
            ->findOneByCommand('coopcycle:tracking:flush');

        if (!$flushTracking) {
            $flushTracking = new ScheduledCommand();
            $flushTracking
                ->setName('Flush tracking')
                ->setCommand('coopcycle:tracking:flush')
                ->setCronExpression('*/10 * * * *')
                ->setPriority(1)
                ->setExecuteImmediately(false)
                ->setDisabled(false);

            $this->scheduledCommandManager->persist($flushTracking);
            $this->scheduledCommandManager->flush();
        }
    }
}
