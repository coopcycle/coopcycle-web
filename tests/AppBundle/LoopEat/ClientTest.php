<?php

namespace Tests\AppBundle\LoopEat;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\LoopEat\Client as LoopEatClient;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ClientTest extends TestCase
{
    use ProphecyTrait;

    private $eventRecorder;
    private $orderNumberAssigner;
    private $stripeManager;

    private $handler;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->jwtEncoder = $this->prophesize(JWTEncoderInterface::class);
        $this->iriConverter = $this->prophesize(IriConverterInterface::class);
        $this->urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $this->cache = $this->prophesize(CacheInterface::class);

        $this->historyContainer = [];
		$history = Middleware::history($this->historyContainer);

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push($history);

        $this->client = new LoopEatClient(
            $this->entityManager->reveal(),
            $this->jwtEncoder->reveal(),
            $this->iriConverter->reveal(),
            $this->urlGenerator->reveal(),
			$this->cache->reveal(),
            new NullLogger(),
            ['handler' => $handlerStack]
        );
    }

    public function testCreateOrder()
    {
    	$restaurant = new Restaurant();

    	$customer = $this->prophesize(CustomerInterface::class);
    	$customer
    		->getLoopeatAccessToken()
    		->willReturn('123456abcdef');

        $order = $this->prophesize(OrderInterface::class);

        $order
        	->getId()
        	->willReturn(1);

        $order
        	->getRestaurant()
        	->willReturn($restaurant);

        $order
        	->getCustomer()
        	->willReturn($customer->reveal());

        $order
        	->getFormatsToDeliverForLoopeat()
        	->willReturn([
        		['format_id' => 1, 'quantity' => 1],
        		['format_id' => 2, 'quantity' => 2],
        	]);

        $order
            ->getLoopeatReturns()
            ->willReturn([
                ['format_id' => 1, 'quantity' => 1],
                ['format_id' => 2, 'quantity' => 1],
            ]);

        $loopeatOrder = [
            'id' => 123,
            'status' => 'pending',
        ];

       	$this->mockHandler->append(
            new Response(200, [], json_encode([
                'data' => [
                    'id' => 123,
                ]
            ])),
             new Response(200, [], json_encode([
                'data' => $loopeatOrder
            ]))
        );

        $result = $this->client->createOrder($order->reveal());

        $transaction = $this->historyContainer[1];

        $request = $transaction['request'];

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/v1/partners/restaurants/123/orders', (string) $request->getUri());

        $body = (string) $request->getBody();
        $payload = json_decode($body, true);

        $this->assertEquals([
        	'order' => [
        		'external_id' => 1,
        		'formats' => [
        			['format_id' => 1, 'quantity' => 1, 'act' => 'deliver'],
    				['format_id' => 2, 'quantity' => 2, 'act' => 'deliver'],
                    ['format_id' => 1, 'quantity' => 1, 'act' => 'pickup'],
                    ['format_id' => 2, 'quantity' => 1, 'act' => 'pickup'],
        		]
        	]
    	], $payload);

    	$this->assertEquals($loopeatOrder, $result);
    }
}
