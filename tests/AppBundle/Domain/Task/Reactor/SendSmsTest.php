<?php

namespace Tests\AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Domain\Task\Reactor\SendSms;
use AppBundle\Entity\Address;
use AppBundle\Entity\User;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Task;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Message\Sms;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendSmsTest extends TestCase
{
    use ProphecyTrait;

    private $sendEmail;

    public function setUp(): void
    {
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);
        $this->phoneNumberUtil = $this->prophesize(PhoneNumberUtil::class);
        $this->urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->orderRepository = $this->prophesize(OrderRepository::class);

        $this->sendSms = new SendSms(
            $this->settingsManager->reveal(),
            $this->orderRepository->reveal(),
            $this->messageBus->reveal(),
            $this->phoneNumberUtil->reveal(),
            $this->urlGenerator->reveal(),
            $this->translator->reveal(),
            'foobar'
        );
    }

    public function testSendsSmsWithSmsDisabled()
    {
        $this->settingsManager->canSendSms()->willReturn(false);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $msg = new Sms('Hello', '+33612345678');

        $this->messageBus
            ->dispatch($msg)
            ->shouldNotBeCalled();

        call_user_func_array($this->sendSms, [ new TaskStarted($pickup) ]);
    }

    public function testSendsSmsForFoodtechOrder()
    {
        $this->settingsManager->canSendSms()->willReturn(true);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $order = $this->prophesize(OrderInterface::class);
        $order->hasVendor()->willReturn(true);

        $this->orderRepository
            ->findOneByTask($pickup)
            ->willReturn($order->reveal());

        $msg = new Sms('Hello', '+33612345678');

        $this->messageBus
            ->dispatch($msg)
            ->shouldNotBeCalled();

        call_user_func_array($this->sendSms, [ new TaskStarted($pickup) ]);
    }

    public function testSendsSmsWithoutTelephone()
    {
        $this->settingsManager->canSendSms()->willReturn(true);

        $pickup = new Task();
        $pickup->setType(Task::TYPE_PICKUP);

        $address = new Address();

        $pickup->setAddress($address);

        $msg = new Sms('Hello', '+33612345678');

        $this->messageBus
            ->dispatch($msg)
            ->shouldNotBeCalled();

        call_user_func_array($this->sendSms, [ new TaskStarted($pickup) ]);
    }

    public function testSendSimpleSmsToRecipient()
    {
        $this->settingsManager->canSendSms()->willReturn(true);

        $phoneNumber = new PhoneNumber();

        $this->phoneNumberUtil->format($phoneNumber, Argument::any())
            ->willReturn('+33612345678');

        $address = new Address();
        $address->setTelephone($phoneNumber);
        $address->setStreetAddress('15, rue de Rivoli Paris');

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff->setAddress($address);

        $this->translator
            ->trans('sms.simple', ['%address%' => '15, rue de Rivoli Paris'])
            ->will(function ($args) {

                Assert::assertArrayHasKey('%address%', $args[1]);

                return sprintf('Our messenger is on its way to %s', $args[1]['%address%']);
            })
            ->shouldBeCalled()
            ;

        $msg = new Sms('Our messenger is on its way to 15, rue de Rivoli Paris', '+33612345678');

        // @see https://github.com/symfony/symfony/issues/33740
        $this->messageBus
            ->dispatch($msg)
            ->shouldBeCalled()
            ->willReturn(new Envelope($msg));

        call_user_func_array($this->sendSms, [ new TaskStarted($dropoff) ]);
    }

    public function testSendSmsWithTrackingToRecipient()
    {
        $this->settingsManager->canSendSms()->willReturn(true);

        $phoneNumber = new PhoneNumber();

        $this->phoneNumberUtil->format($phoneNumber, Argument::any())
            ->willReturn('+33612345678');

        $address = new Address();
        $address->setTelephone($phoneNumber);
        $address->setStreetAddress('15, rue de Rivoli Paris');

        $dropoff = new Task();
        $dropoff->setType(Task::TYPE_DROPOFF);
        $dropoff->setAddress($address);

        $delivery = $this->prophesize(Delivery::class);
        $delivery->getId()->willReturn(1);

        $dropoff->setDelivery($delivery->reveal());

        $this->urlGenerator
            ->generate('public_delivery', ['hashid' => 'p5oXEQvJ'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://bit.ly/abcdef')
            ;

        $this->translator
            ->trans('sms.with_tracking', [
                '%address%' => '15, rue de Rivoli Paris',
                '%link%' => 'http://bit.ly/abcdef'
            ])
            ->will(function ($args) {

                Assert::assertArrayHasKey('%address%', $args[1]);
                Assert::assertArrayHasKey('%link%', $args[1]);

                return sprintf('Our messenger is on its way to %s. Track delivery at %s', $args[1]['%address%'], $args[1]['%link%']);
            })
            ->shouldBeCalled()
            ;

        $msg = new Sms(
            'Our messenger is on its way to 15, rue de Rivoli Paris. Track delivery at http://bit.ly/abcdef',
            '+33612345678'
        );

        // @see https://github.com/symfony/symfony/issues/33740
        $this->messageBus
            ->dispatch($msg)
            ->shouldBeCalled()
            ->willReturn(new Envelope($msg));

        call_user_func_array($this->sendSms, [ new TaskStarted($dropoff) ]);
    }
}
