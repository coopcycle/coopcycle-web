<?php

declare(strict_types=1);

namespace Tests\AppBundle\Form;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Form\ProductOptionType;
use AppBundle\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * The weight is only used by pricing rules,
 * so the fields are only displayed when the contract of the shop uses a pricing rule set.
 */
class ProductWeightFieldsTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();

        $container->get('fidry_alice_data_fixtures.loader.doctrine')->load([
            __DIR__.'/../../../fixtures/ORM/sylius_locales.yml',
            __DIR__.'/../../../fixtures/ORM/restaurant.yml',
        ]);

        $this->formFactory = $container->get(FormFactoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    private function findRestaurant(): LocalBusiness
    {
        return $this->entityManager
            ->getRepository(LocalBusiness::class)
            ->findOneBy(['name' => 'Bistro']);
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setCurrentLocale('en');
        $product->setFallbackLocale('en');

        return $product;
    }

    private function createProductOption(): ProductOption
    {
        $productOption = new ProductOption();
        $productOption->setCurrentLocale('en');
        $productOption->setFallbackLocale('en');

        return $productOption;
    }

    public function testProductHasNoWeightWithFlatPrices(): void
    {
        $restaurant = $this->findRestaurant();

        $form = $this->formFactory->create(ProductType::class, $this->createProduct(), [
            'owner' => $restaurant,
        ]);

        $this->assertFalse($form->has('weight'));
    }

    public function testProductHasAWeightWithVariableCustomerAmount(): void
    {
        $restaurant = $this->findRestaurant();
        $restaurant->getContract()->setVariableCustomerAmountEnabled(true);

        $form = $this->formFactory->create(ProductType::class, $this->createProduct(), [
            'owner' => $restaurant,
        ]);

        $this->assertTrue($form->has('weight'));
    }

    public function testProductHasAWeightWithVariableDeliveryPrice(): void
    {
        $restaurant = $this->findRestaurant();
        $restaurant->getContract()->setVariableDeliveryPriceEnabled(true);

        $form = $this->formFactory->create(ProductType::class, $this->createProduct(), [
            'owner' => $restaurant,
        ]);

        $this->assertTrue($form->has('weight'));
    }

    public function testProductOptionValuesHaveNoWeightByDefault(): void
    {
        $form = $this->formFactory->create(ProductOptionType::class, $this->createProductOption());

        $prototype = $form->get('values')->getConfig()->getAttribute('prototype');

        $this->assertFalse($prototype->has('weight'));
    }

    public function testProductOptionValuesHaveAWeightWhenAsked(): void
    {
        $form = $this->formFactory->create(ProductOptionType::class, $this->createProductOption(), [
            'with_weight' => true,
        ]);

        $prototype = $form->get('values')->getConfig()->getAttribute('prototype');

        $this->assertTrue($prototype->has('weight'));
    }
}
