<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Controller\RestaurantController;
use AppBundle\Entity\Address;
use AppBundle\Entity\Contract;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Form\Checkout\Action\Validator\AddProductToCart as AssertAddProductToCart;
use AppBundle\Sylius\Cart\RestaurantResolver;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\LazyProductVariantResolverInterface;
use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use AppBundle\Utils\OptionsPayloadConverter;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\RestaurantFilter;
use AppBundle\Service\SettingsManager;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use SimpleBus\SymfonyBridge\Bus\EventBus;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FindOneByCodeRepository extends SyliusEntityRepository
{
    public function findOneByCode($code)
    {
    }
}

class RestaurantControllerTest extends WebTestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        parent::setUp();

        // FIXME
        // Find out why env is not test sometimes
        self::bootKernel(['environment' => 'test']);

        $this->objectManager = $this->prophesize(EntityManagerInterface::class);
        $this->uploaderHelper = $this->prophesize(UploaderHelper::class);
        $this->validator = $this->prophesize(ValidatorInterface::class);
        $this->productRepository = $this->prophesize(FindOneByCodeRepository::class);
        $this->orderItemRepository = $this->prophesize(RepositoryInterface::class);
        $this->orderItemFactory = $this->prophesize(FactoryInterface::class);
        $this->productVariantResolver = $this->prophesize(LazyProductVariantResolverInterface::class);
        $this->optionsPayloadConverter = $this->prophesize(OptionsPayloadConverter::class);
        $this->orderItemQuantityModifier = $this->prophesize(OrderItemQuantityModifierInterface::class);
        $this->orderModifier = $this->prophesize(OrderModifierInterface::class);
        $this->orderTimeHelper = $this->prophesize(OrderTimeHelper::class);
        $this->restaurantResolver = $this->prophesize(RestaurantResolver::class);
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $this->restaurantFilter = $this->prophesize(RestaurantFilter::class);

        $this->localBusinessRepository = $this->prophesize(LocalBusinessRepository::class);

        $this->doctrine = $this->prophesize(ManagerRegistry::class);
        $this->doctrine
            ->getRepository(LocalBusiness::class)
            ->willReturn($this->localBusinessRepository->reveal());

        // Use the "real" serializer
        $this->serializer = static::$kernel->getContainer()->get('serializer');

        $this->eventDispatcher
            ->dispatch(Argument::type('object'), Argument::type('string'))
            ->will(function ($args) {

                return $args[0];
            });

        $container = $this->prophesize(ContainerInterface::class);
        $container
            ->has('doctrine')
            ->willReturn(true);
        $container
            ->get('doctrine')
            ->willReturn($this->doctrine->reveal());

        $parameterBag = $this->prophesize(ParameterBagInterface::class);
        $parameterBag->get('country_iso')->willReturn('fr');
        $parameterBag->get('sylius_cart_restaurant_session_key_name')->willReturn('foo');

        $container
            ->has('parameter_bag')
            ->willReturn(true);
        $container
            ->get('parameter_bag')
            ->willReturn($parameterBag->reveal());

        $eventBus = $this->prophesize(EventBus::class);
        $jwtTokenManager = $this->prophesize(JWTTokenManagerInterface::class);

        $this->controller = new RestaurantController(
            $this->objectManager->reveal(),
            $this->validator->reveal(),
            $this->productRepository->reveal(),
            $this->orderItemRepository->reveal(),
            $this->orderItemFactory->reveal(),
            $this->productVariantResolver->reveal(),
            $this->orderItemQuantityModifier->reveal(),
            $this->orderModifier->reveal(),
            $this->orderTimeHelper->reveal(),
            $this->serializer,
            $this->restaurantFilter->reveal(),
            $eventBus->reveal(),
            $jwtTokenManager->reveal()
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
        $restaurant->setContract(new Contract());
        $this->setId($restaurant, 1);

        // Don't use a mock for the cart
        // because annotation reader won't work (for serialization)
        // https://github.com/doctrine/annotations/issues/186
        $cart = new Order();
        $cart->setRestaurant($restaurant);

        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(true);
        $product->hasOptions()->willReturn(true);

        $restaurant->getProducts()->add($product->reveal());

        $this->localBusinessRepository->find(1)->willReturn($restaurant);

        $cartContext = $this->prophesize(CartContextInterface::class);
        $translator = $this->prophesize(TranslatorInterface::class);

        $cartContext
            ->getCart()
            ->willReturn($cart);

        $this->optionsPayloadConverter->convert($product->reveal(), [
                [
                    'code' => $productOptionValueCode,
                    'quantity' => 3,
                ]
            ])
            ->willReturn(new \SplObjectStorage());

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
        $errors->count()->willReturn(0);
        $errors->rewind()->shouldBeCalled();
        $errors->valid()->shouldBeCalled();

        $this->validator
            ->validate(Argument::type('object'), Argument::any())
            ->will(function ($args) use ($cart, $errors) {

                if ($args[0] === $cart) {

                    return $errors->reveal();
                }

                return $errors->reveal();
            });

        $response = $this->controller->addProductToCartAction(1, $productCode, $request,
            $cartContext->reveal(),
            $translator->reveal(),
            $this->restaurantResolver->reveal(),
            $this->optionsPayloadConverter->reveal(),
            $this->eventDispatcher->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('cart', $data);
        $this->assertArrayHasKey('times', $data);
        $this->assertArrayHasKey('errors', $data);

        $this->assertArrayHasKey('vendor', $data['cart']);

        $vendor = $data['cart']['vendor'];

        $this->assertEquals(['latlng' => [48.856613, 2.352222]], $vendor['address']);
        $this->assertEquals(['delivery'], $vendor['fulfillmentMethods']);
        $this->assertFalse($vendor['variableCustomerAmountEnabled']);
    }

    public function testAddProductToCartActionWithRestaurantMismatch(): void
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
        $restaurant->setContract(new Contract());
        $this->setId($restaurant, 1);

        $otherRestaurant = new Restaurant();
        $otherRestaurant->setAddress($restaurantAddress);
        $this->setId($otherRestaurant, 2);

        // Don't use a mock for the cart
        // because annotation reader won't work (for serialization)
        // https://github.com/doctrine/annotations/issues/186
        $cart = new Order();
        $cart->setRestaurant($otherRestaurant);

        $product = $this->prophesize(ProductInterface::class);
        $product->isEnabled()->willReturn(true);
        $product->hasOptions()->willReturn(true);

        $restaurant->getProducts()->add($product->reveal());

        $this->localBusinessRepository->find(1)->willReturn($restaurant);

        $cartContext = $this->prophesize(CartContextInterface::class);
        $translator = $this->prophesize(TranslatorInterface::class);

        $cartContext
            ->getCart()
            ->willReturn($cart);

        $this->productRepository
            ->findOneByCode($productCode)
            ->willReturn($product->reveal());

        $errors = $this->prophesize(ConstraintViolationListInterface::class);

        $this->validator
            ->validate(Argument::type('object'), Argument::any())
            ->will(function ($args) use ($cart, $errors) {

                if ($args[0] === $cart) {

                    return $errors->reveal();
                }

                $errs = new ConstraintViolationList();
                $errs->add(new ConstraintViolation('Restaurant mismatch', null, [], '', 'restaurant', null));

                return $errs;
            });

        $response = $this->controller->addProductToCartAction(1, $productCode, $request,
            $cartContext->reveal(),
            $translator->reveal(),
            $this->restaurantResolver->reveal(),
            $this->optionsPayloadConverter->reveal(),
            $this->eventDispatcher->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('cart', $data);
        $this->assertArrayHasKey('times', $data);
        $this->assertArrayHasKey('errors', $data);

        $this->assertArrayHasKey('restaurant', $data['errors']);
        $this->assertCount(1, $data['errors']['restaurant']);
        $this->assertEquals('Restaurant mismatch', $data['errors']['restaurant'][0]['message']);
    }
}
