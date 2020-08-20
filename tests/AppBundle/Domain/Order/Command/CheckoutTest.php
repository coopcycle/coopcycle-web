<?php

namespace Tests\AppBundle\Domain\Order\Command;

use AppBundle\Domain\Order\Command\Checkout;
use AppBundle\Entity\Sylius\Order;
use PHPUnit\Framework\TestCase;

class CheckoutTest extends TestCase
{
    public function testWithString()
    {
        $order = new Order();
        $command = new Checkout($order, 'tok_123456');

        $this->assertEquals('tok_123456', $command->getStripeToken());
        $this->assertEquals(['stripeToken' => 'tok_123456'], $command->getData());
    }

    public function testWithArray()
    {
        $order = new Order();
        $command = new Checkout($order, ['stripeToken' => 'tok_123456']);

        $this->assertEquals('tok_123456', $command->getStripeToken());
        $this->assertEquals(['stripeToken' => 'tok_123456'], $command->getData());
    }
}
