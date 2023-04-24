<?php

namespace AppBundle\Sylius\Product;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Quote;
use AppBundle\Service\SettingsManager;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Product\Repository\ProductRepositoryInterface;
use Sylius\Component\Product\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Repository\TaxCategoryRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Doctrine\Persistence\ManagerRegistry;

class ProductVariantFactory implements ProductVariantFactoryInterface
{
    /**
     * @var ProductVariantFactoryInterface
     */
    private $factory;

    private $productRepository;

    private $productVariantRepository;

    private $taxCategoryRepository;

    private $settingsManager;

    private $translator;

    public function __construct(
        ProductVariantFactoryInterface $factory,
        ProductRepositoryInterface $productRepository,
        ProductVariantRepositoryInterface $productVariantRepository,
        TaxCategoryRepositoryInterface $taxCategoryRepository,
        SettingsManager $settingsManager,
        TranslatorInterface $translator)
    {
        $this->factory = $factory;
        $this->productRepository = $productRepository;
        $this->productVariantRepository = $productVariantRepository;
        $this->taxCategoryRepository = $taxCategoryRepository;
        $this->settingsManager = $settingsManager;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function createNew()
    {
        return $this->factory->createNew();
    }

    /**
     * {@inheritdoc}
     */
    public function createForProduct(ProductInterface $product): ProductVariantInterface
    {
        return $this->factory->createForProduct($product);
    }

    /**
     * @param Delivery $delivery
     * @param int $price
     */
    public function createForDelivery(Delivery $delivery, int $price): ProductVariantInterface
    {
        $log = new Logger('createForDelivery');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream
        $log->warning('createForDelivery - Test point 1');
        $hash = sprintf('%s-%d-%d', $delivery->getVehicle(), $delivery->getDistance(), $price);
        $code = sprintf('CPCCL-ODDLVR-%s', strtoupper(substr(sha1($hash), 0, 7)));
        $log->warning('createForDelivery - Test point 2');
        $log->warning('createForDelivery - $delivery->getDistance(): '. $delivery->getDistance());
        $log->warning('createForDelivery - $price: '. $price);
        if ($productVariant = $this->productVariantRepository->findOneByCode($code)) {
            $log->warning('createForDelivery - Test point 3');
            return $productVariant;
        }

        $product = $this->productRepository->findOneByCode('CPCCL-ODDLVR');

        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $taxCategory = $this->taxCategoryRepository->findOneBy([
            'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT'
        ]);
        $log->warning('createForDelivery - Test point 4');
        $productVariant = $this->createForProduct($product);

        $name = sprintf('%s, %s km',
            $this->translator->trans(sprintf('vehicle.%s', $delivery->getVehicle())),
            (string) number_format($delivery->getDistance() / 1000, 2)
        );
        $log->warning('createForDelivery - Test point 5');
        $productVariant->setName($name);
        $productVariant->setPosition(1);
        $log->warning('createForDelivery - Test point 6');
        $productVariant->setPrice($price);
        $productVariant->setTaxCategory($taxCategory);
        $productVariant->setCode($code);
        $log->warning('createForDelivery - Test point 7');
        return $productVariant;
    }

    /**
     * @param Quote $quote
     * @param int $price
     */
    public function createForQuote(Quote $quote, int $price): ProductVariantInterface
    {
        $log = new Logger('createForQuote');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream
        $log->warning('createForQuote - Test point 1');
        $hash = sprintf('%s-%d-%d', $quote->getVehicle(), $quote->getDistance(), $price);
        $code = sprintf('CPCCL-ODDLVR-%s', strtoupper(substr(sha1($hash), 0, 7)));
        $log->warning('createForQuote - Test point 2');
        if ($productVariant = $this->productVariantRepository->findOneByCode($code)) {
            $log->warning('createForQuote - Test point 3');
            return $productVariant;
        }

        $product = $this->productRepository->findOneByCode('CPCCL-ODDLVR');

        $subjectToVat = $this->settingsManager->get('subject_to_vat');

        $taxCategory = $this->taxCategoryRepository->findOneBy([
            'code' => $subjectToVat ? 'SERVICE' : 'SERVICE_TAX_EXEMPT'
        ]);
        $log->warning('createForQuote - Test point 4');
        $productVariant = $this->createForProduct($product);

        $name = sprintf('%s, %s km',
            $this->translator->trans(sprintf('vehicle.%s', $quote->getVehicle())),
            (string) number_format($quote->getDistance() / 1000, 2)
        );
        $log->warning('createForQuote - Test point 5');
        $productVariant->setName($name);
        $productVariant->setPosition(1);
        $log->warning('createForQuote - Test point 6');
        $productVariant->setPrice($price);
        $productVariant->setTaxCategory($taxCategory);
        $productVariant->setCode($code);
        $log->warning('createForQuote - Test point 7');
        return $productVariant;
    }
}
