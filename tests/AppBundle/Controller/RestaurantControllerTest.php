<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Controller\RestaurantController;
use AppBundle\DataType\NumRange;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductOptionInterface;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Service\SettingsManager;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityRepository;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository as SyliusEntityRepository;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class FindOneByCodeRepository extends SyliusEntityRepository
{
    public function findOneByCode($code)
    {
    }
}

class RestaurantControllerTest extends WebTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // FIXME
        // Find out why env is not test sometimes
        self::bootKernel(['environment' => 'test']);

        $this->objectManager = $this->prophesize(ObjectManager::class);
        $this->seoPage = $this->prophesize(SeoPageInterface::class);
        $this->uploaderHelper = $this->prophesize(UploaderHelper::class);
        $this->validator = $this->prophesize(ValidatorInterface::class);
        $this->productRepository = $this->prophesize(FindOneByCodeRepository::class);
        $this->orderItemRepository = $this->prophesize(RepositoryInterface::class);
        $this->orderItemFactory = $this->prophesize(FactoryInterface::class);
        $this->productVariantResolver = $this->prophesize(LazyProductVariantResolverInterface::class);
        $this->productOptionValueRepository = $this->prophesize(FindOneByCodeRepository::class);
        $this->orderItemQuantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $this->orderModifier = $this->prophesize(OrderModifierInterface::class);
        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);

        $this->restaurantRepository = $this->prophesize(EntityRepository::class);

        $this->doctrine = $this->prophesize(ManagerRegistry::class);
        $this->doctrine
            ->getRepository(Restaurant::class)
            ->willReturn($this->restaurantRepository->reveal());

        // Use the "real" serializer
        $this->serializer = static::$kernel->getContainer()->get('serializer');

        $container = $this->prophesize(ContainerInterface::class);
        $container
            ->has('doctrine')
            ->willReturn(true);
        $container
            ->get('doctrine')
            ->willReturn($this->doctrine->reveal());

        $parameterBag = $this->prophesize(ParameterBagInterface::class);
        $parameterBag->get('sylius_cart_restaurant_session_key_name')->willReturn('foo');

        $container
            ->has('parameter_bag')
            ->willReturn(true);
        $container
            ->get('parameter_bag')
            ->willReturn($parameterBag->reveal());

        $this->controller = new RestaurantController(
            $this->objectManager->reveal(),
            $this->seoPage->reveal(),
            $this->uploaderHelper->reveal(),
            $this->validator->reveal(),
            $this->productRepository->reveal(),
            $this->orderItemRepository->reveal(),
            $this->orderItemFactory->reveal(),
            $this->productVariantResolver->reveal(),
            $this->productOptionValueRepository->reveal(),
            $this->orderItemQuantityModifier->reveal(),
            $this->orderModifier->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->serializer
        );

        $this->controller->setContainer($container->reveal());
    }

    private function setId($object, $id)
    {
        $property = new \ReflectionProperty($object, 'id');
        $property->setAccessible(true);
        $property->setValue($object, $id);
    }

    public function testAddProductToCartAction(): void
    {
        $productCode = Uuid::uuid4()->toString();
        $productOptionValueCode = Uuid::uuid4()->toString();

        $session = new Session(new MockArraySessionStorage());

        $request = Request::create('/restaurant/{id}/cart/product/{code}', 'POST', [
            'options' => [
                [
                    'code' => $productOptionValueCode,
                    'quantity' => 3,
                ]
            ]
        ]);
        $request->setSession($session);

        $restaurantAddress = new Address();
        $restaurantAddress->setGeo(new GeoCoordinates(48.856613, 2.352222));
        $this->setId($restaurantAddress, 1);

        $restaurant = new Restaurant();
        $restaurant->setAddress($restaurantAddress);
        $this->setId($restaurant, 1);

        // Don't use a mock for the cart
        // because annotation reader won't work (for serialization)
        // https://github.com/doctrine/annotations/issues/186
        $cart = new Order();
        $cart->setRestaurant($restaurant);

        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(true);
        $product->getRestaurant()->willReturn($restaurant);
        $product->hasOptions()->willReturn(true);
        $product->setRestaurant(Argument::type(Restaurant::class))->shouldBeCalled();

        $restaurant->addProduct($product->reveal());

        $this->restaurantRepository->find(1)->willReturn($restaurant);

        $cartContext = $this->prophesize(CartContextInterface::class);
        $translator = $this->prophesize(TranslatorInterface::class);

        $cartContext
            ->getCart()
            ->willReturn($cart);

        $productOption = $this->prophesize(ProductOptionInterface::class);
        $productOptionValue = $this->prophesize(ProductOptionValueInterface::class);

        $valuesRange = new NumRange();
        $valuesRange->setLower(1);
        $valuesRange->setUpper(5);

        $productOption->isAdditional()->willReturn(true);
        $productOption->getValuesRange()->willReturn($valuesRange);

        $productOptionValue->getOption()->willReturn($productOption->reveal());

        $product->hasOption($productOption->reveal())->willReturn(true);
        $product->hasOptionValue($productOptionValue->reveal())->willReturn(true);

        $this->productOptionValueRepository
            ->findOneByCode($productOptionValueCode)
            ->willReturn($productOptionValue->reveal());

        $this->productRepository
            ->findOneByCode($productCode)
            ->willReturn($product->reveal());

        $orderItem = $this->prophesize(OrderItemInterface::class);

        $this->orderItemFactory
            ->createNew()
            ->willReturn($orderItem->reveal());

        $variant = $this->prophesize(ProductVariantInterface::class);
        $variant->getPrice()->willReturn(900);

        $this->productVariantResolver
            ->getVariantForOptionValues($product->reveal(), Argument::type(\SplObjectStorage::class))
            ->willReturn($variant->reveal());

        $errors = $this->prophesize(ConstraintViolationListInterface::class);

        $this->validator
            ->validate($cart)
            ->willReturn($errors->reveal());

        $response = $this->controller->addProductToCartAction(1, $productCode, $request, $cartContext->reveal(), $translator->reveal());

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('cart', $data);
        $this->assertArrayHasKey('times', $data);
        $this->assertArrayHasKey('errors', $data);

        $expectedRestaurant = [
            'id' => 1,
            'address' => [
                'latlng' => [48.856613, 2.352222]
            ]
        ];

        $this->assertEquals($expectedRestaurant, $data['cart']['restaurant']);
    }
}
