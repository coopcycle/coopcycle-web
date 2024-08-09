<?php

namespace Tests\AppBundle\Controller;

use AppBundle\Controller\StripeController;
use AppBundle\Entity\Sylius\Payment;
use AppBundle\Service\OrderManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Hashids\Hashids;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use SimpleBus\Message\Bus\MessageBus;
use Stripe;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use PHPUnit\Framework\TestCase;

class StripeControllerTest extends TestCase
{
    use ProphecyTrait;

    private $controller;

    private $entityManager;

    public function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->orderManager = $this->prophesize(OrderManager::class);
        $this->eventBus = $this->prophesize(MessageBus::class);
        $this->orderNumberAssigner = $this->prophesize(OrderNumberAssignerInterface::class);
        $this->stripeManager = $this->prophesize(StripeManager::class);
        $this->adjustmentFactory = $this->prophesize(AdjustmentFactoryInterface::class);

        $orderFactory = $this->prophesize(FactoryInterface::class);

        $this->secret = '123456';

        $this->controller = new StripeController(
            $this->secret,
            true,
            $this->entityManager->reveal(),
            $this->adjustmentFactory->reveal(),
            new NullLogger()
        );
    }

    private function createPaymentIntent($intentStatus, $nextActionType = null, $clientSecret = null): Stripe\PaymentIntent
    {
        $payload = [
            'id' => 'pi_12345678',
            'status' => $intentStatus,
            'next_action' => null,
            'client_secret' => ''
        ];

        if ($nextActionType) {
            $payload['next_action'] = [ 'type' => $nextActionType ];
        }
        if ($clientSecret) {
            $payload['client_secret'] = $clientSecret;
        }

        return Stripe\PaymentIntent::constructFrom($payload);
    }

    private function createSetupIntent($intentStatus): Stripe\SetupIntent
    {
        $payload = [
            'id' => 'seti_12345678',
            'status' => $intentStatus,
        ];

        return Stripe\SetupIntent::constructFrom($payload);
    }

    public function testCreatePaymentActionWithNextAction()
    {
        $order = $this->prophesize(OrderInterface::class);

        $payment = new Payment();
        $payment->setOrder($order->reveal());

        $paymentRepository = $this->prophesize(ObjectRepository::class);
        $paymentRepository->find(1)->willReturn($payment);

        $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->willReturn($paymentRepository->reveal());

        $paymentIntent = $this->createPaymentIntent('requires_source_action', 'use_stripe_sdk', '123456');

        $this->stripeManager->createIntent($payment, false)->willReturn($paymentIntent);

        $payload = [
            'payment_method_id' => 'pm_123456'
        ];

        $hashids = new Hashids($this->secret, 8);
        $hashId = $hashids->encode(1);

        $request = Request::create(sprintf('/stripe/payment/%s/create-intent', $hashId), 'POST',
            $parameters = [],
            $cookies = [],
            $files = [],
            $server = [],
            $content = json_encode($payload));

        $this->entityManager->flush()->shouldBeCalled();

        $response = $this->controller->createPaymentIntentAction(
            $hashId,
            $request,
            $this->orderNumberAssigner->reveal(),
            $this->stripeManager->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('requires_action', $data);
        $this->assertArrayHasKey('payment_intent_client_secret', $data);

        $this->assertEquals(true, $data['requires_action']);
        $this->assertEquals('123456', $data['payment_intent_client_secret']);
    }

    public function testConfirmPaymentActionWithoutNextAction()
    {
        $order = $this->prophesize(OrderInterface::class);

        $payment = new Payment();
        $payment->setOrder($order->reveal());

        $paymentRepository = $this->prophesize(ObjectRepository::class);
        $paymentRepository->find(1)->willReturn($payment);

        $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->willReturn($paymentRepository->reveal());

        $paymentIntent = $this->createPaymentIntent('requires_capture');

        $this->stripeManager->createIntent($payment, false)->willReturn($paymentIntent);

        $payload = [
            'payment_method_id' => 'pm_123456'
        ];

        $hashids = new Hashids($this->secret, 8);
        $hashId = $hashids->encode(1);

        $request = Request::create(sprintf('/stripe/payment/%s/create-intent', $hashId), 'POST',
            $parameters = [],
            $cookies = [],
            $files = [],
            $server = [],
            $content = json_encode($payload));

        $this->entityManager->flush()->shouldBeCalled();

        $response = $this->controller->createPaymentIntentAction(
            $hashId,
            $request,
            $this->orderNumberAssigner->reveal(),
            $this->stripeManager->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('requires_action', $data);
        $this->assertArrayHasKey('payment_intent', $data);

        $this->assertEquals(false, $data['requires_action']);
        $this->assertEquals('pi_12345678', $data['payment_intent']);
    }

    public function testConfirmPaymentActionWithInvalidPayload()
    {
        $order = $this->prophesize(OrderInterface::class);

        $payment = new Payment();
        $payment->setOrder($order->reveal());

        $paymentRepository = $this->prophesize(ObjectRepository::class);
        $paymentRepository->find(1)->willReturn($payment);

        $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->willReturn($paymentRepository->reveal());

        $paymentIntent = $this->createPaymentIntent('requires_capture');

        $this->stripeManager->createIntent($payment)->shouldNotBeCalled();

        $hashids = new Hashids($this->secret, 8);
        $hashId = $hashids->encode(1);

        $request = Request::create(sprintf('/stripe/payment/%s/create-intent', $hashId), 'POST',
            $parameters = [],
            $cookies = [],
            $files = [],
            $server = [],
            $content = json_encode([]));

        $this->entityManager->flush()->shouldNotBeCalled();

        $response = $this->controller->createPaymentIntentAction(
            $hashId,
            $request,
            $this->orderNumberAssigner->reveal(),
            $this->stripeManager->reveal(),
            $this->entityManager->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data['error']);
    }

    public function testCreateSetupIntentOrAttachPMActionWithSetupIntent()
    {
        $order = $this->prophesize(OrderInterface::class);

        $payment = new Payment();
        $payment->setOrder($order->reveal());

        $paymentRepository = $this->prophesize(ObjectRepository::class);
        $paymentRepository->find(1)->willReturn($payment);

        $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->willReturn($paymentRepository->reveal());

        $setupIntent = $this->createSetupIntent('succeeded');

        $this->stripeManager->createSetupIntent($payment, 'pm_123456')->willReturn($setupIntent);

        $payload = [
            'payment_method_to_save' => 'pm_123456'
        ];

        $hashids = new Hashids($this->secret, 8);
        $hashId = $hashids->encode(1);

        $request = Request::create(sprintf('/stripe/payment/%s/create-setup-intent-or-attach-pm', $hashId), 'POST',
            $parameters = [],
            $cookies = [],
            $files = [],
            $server = [],
            $content = json_encode($payload));

        // $this->entityManager->flush()->shouldBeCalled();

        $response = $this->controller->createSetupIntentOrAttachPMAction(
            $hashId,
            $request,
            $this->stripeManager->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCreateSetupIntentOrAttachPMActionWithAttach()
    {
        $order = $this->prophesize(OrderInterface::class);

        $payment = new Payment();
        $payment->setOrder($order->reveal());

        $paymentRepository = $this->prophesize(ObjectRepository::class);
        $paymentRepository->find(1)->willReturn($payment);

        $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->willReturn($paymentRepository->reveal());

        $setupIntent = $this->createSetupIntent('requires_action');
        
        $this->stripeManager->createSetupIntent($payment, 'pm_123456')->willReturn($setupIntent);

        $payload = [
            'payment_method_to_save' => 'pm_123456'
        ];

        $hashids = new Hashids($this->secret, 8);
        $hashId = $hashids->encode(1);

        $request = Request::create(sprintf('/stripe/payment/%s/create-setup-intent-or-attach-pm', $hashId), 'POST',
            $parameters = [],
            $cookies = [],
            $files = [],
            $server = [],
            $content = json_encode($payload));

        $this->entityManager->flush()->shouldBeCalled();

        $response = $this->controller->createSetupIntentOrAttachPMAction(
            $hashId,
            $request,
            $this->stripeManager->reveal()
        );

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertArrayHasKey('save_payment_method', $payment->getDetails());
        $this->assertEquals(true, $payment->hasToSavePaymentMethod());
        $this->assertArrayHasKey('payment_method_to_save', $payment->getDetails());
        $this->assertEquals('pm_123456', $payment->getPaymentMethodToSave());
    }
}
