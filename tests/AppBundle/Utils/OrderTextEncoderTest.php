<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\Vendor;
use AppBundle\Utils\OrderTextEncoder;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Order\AdjustmentInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use AppBundle\Utils\PriceFormatter;
use Doctrine\Common\Collections\ArrayCollection;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment as TwigEnvironment;

class OrderTextEncoderTest extends KernelTestCase
{
    use ProphecyTrait;

    private $eventBus;
    private $reactor;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        // @see https://symfony.com/blog/new-in-symfony-4-1-simpler-service-testing
        $twig = self::$container->get(TwigEnvironment::class);

        $this->encoder = new OrderTextEncoder($twig);

        $this->priceFormatter = self::$container->get(PriceFormatter::class);
    }

    private function createOrder(int $tipAmount = 0): OrderInterface
    {
        $order = $this->prophesize(OrderInterface::class);

        $order->getId()->willReturn(1);
        $order->getNumber()->willReturn('ABC');

        $vendor1 = $this->prophesize(Vendor::class);
        $vendor1->getName()->willReturn('Foo');

        $vendor2 = $this->prophesize(Vendor::class);
        $vendor2->getName()->willReturn('Hello');

        $hash = new \SplObjectStorage();

        $variant1 = $this->prophesize(ProductVariantInterface::class);
        $variant1->getName()->willReturn('Pizza');

        $item1 = $this->prophesize(OrderItemInterface::class);
        $item1->getVariant()->willReturn($variant1->reveal());
        $item1->getQuantity()->willReturn(1);
        $item1->getAdjustments(Argument::type('string'))->willReturn(new ArrayCollection());

        $variant2 = $this->prophesize(ProductVariantInterface::class);
        $variant2->getName()->willReturn('Hamburger');

        $item2 = $this->prophesize(OrderItemInterface::class);
        $item2->getVariant()->willReturn($variant2->reveal());
        $item2->getQuantity()->willReturn(2);
        $item2->getAdjustments(Argument::type('string'))->willReturn(new ArrayCollection());

        $hash[$vendor1->reveal()] = [ $item1->reveal() ];
        $hash[$vendor2->reveal()] = [ $item2->reveal() ];

        $order
            ->getItemsGroupedByVendor()
            ->willReturn($hash);

        $order
            ->getReusablePackagingPledgeReturn()
            ->willReturn(0);

        $order
            ->getAdjustmentsTotal(AdjustmentInterface::TIP_ADJUSTMENT)
            ->willReturn($tipAmount);

        return $order->reveal();
    }

    public function testEncodeForHub()
    {
        $order = $this->createOrder();

        $output = $this->encoder->encode($order, 'txt');

        $expected = <<<EOT
Commande ABC (#1)

---

Foo
===

  Pizza × 1

Hello
=====

  Hamburger × 2



EOT;

        $this->assertEquals($expected, $output);
    }

    public function testEncodeWithTip()
    {
        $formattedPrice = $this->priceFormatter->formatWithSymbol(500);

        $order = $this->createOrder($tipAmount = 500);

        $output = $this->encoder->encode($order, 'txt');

        $expected = <<<EOT
Commande ABC (#1)

---

Foo
===

  Pizza × 1

Hello
=====

  Hamburger × 2


---

Pourboire {$formattedPrice}

EOT;

        $this->assertEquals($expected, $output);
    }
}
