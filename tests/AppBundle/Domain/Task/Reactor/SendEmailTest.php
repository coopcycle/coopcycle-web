<?php

namespace Tests\AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Domain\Task\Reactor\SendEmail;
use AppBundle\Entity\User;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

class SendEmailTest extends TestCase
{
    use ProphecyTrait;

    private $sendEmail;

    public function setUp(): void
    {
        $this->emailManager = $this->prophesize(EmailManager::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);

        $this->sendEmail = new SendEmail(
            $this->emailManager->reveal(),
            $this->settingsManager->reveal()
        );
    }

    public function testDoesNothingWithStandaloneTask()
    {
        $task = new Task();

        $this->emailManager
            ->sendTo(Argument::type('string'), Argument::type('string'))
            ->shouldNotBeCalled();

        call_user_func_array($this->sendEmail, [ new TaskDone($task, 'Lorem ipsum') ]);
    }

    public function testDoesNothingWithFoodtechOrder()
    {
        $task = new Task();

        $delivery = new Delivery();

        $order = $this->prophesize(OrderInterface::class);
        $order
            ->isFoodtech()
            ->willReturn(true);

        $delivery->setOrder($order->reveal());

        $task->setDelivery($delivery);

        $this->emailManager
            ->sendTo(Argument::type('string'), Argument::type('string'))
            ->shouldNotBeCalled();

        call_user_func_array($this->sendEmail, [ new TaskDone($task, 'Lorem ipsum') ]);
    }

    public function testDoesNothingWithDeliveryNotBelongingToStore()
    {
        $task = new Task();

        $delivery = new Delivery();

        $task->setDelivery($delivery);

        $this->emailManager
            ->sendTo(Argument::type('string'), Argument::type('string'))
            ->shouldNotBeCalled();

        call_user_func_array($this->sendEmail, [ new TaskDone($task, 'Lorem ipsum') ]);
    }

    public function testSendsEmailToStoreOwners()
    {
        $store = new Store();

        $bob = $this->prophesize(User::class);
        $bob->getFullName()->willReturn('Bob');
        $bob->getEmail()->willReturn('bob@acme.com');

        $sarah = $this->prophesize(User::class);
        $sarah->getFullName()->willReturn('Sarah');
        $sarah->getEmail()->willReturn('sarah@acme.com');

        $store->getOwners()->add($bob->reveal());
        $store->getOwners()->add($sarah->reveal());

        $delivery = new Delivery();
        $delivery->setStore($store);

        $task = new Task();
        $task->setDelivery($delivery);

        $message = new \Swift_Message();

        $this->emailManager
            ->createTaskCompletedMessage($task)
            ->willReturn($message);

        $this->emailManager
            ->sendTo($message, Argument::that(function ($to) {
                return array_key_exists('bob@acme.com', $to) && array_key_exists('sarah@acme.com', $to);
            }))
            ->shouldBeCalled();

        call_user_func_array($this->sendEmail, [ new TaskDone($task, 'Lorem ipsum') ]);
    }
}
