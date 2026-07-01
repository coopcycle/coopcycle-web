<?php

namespace Tests\Integration\Zelty;

use AppBundle\Entity\Address;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\OrderItem;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Entity\Sylius\ProductVariant;
use AppBundle\Integration\Zelty\ZeltyOrderNormalizer;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class ZeltyOrderNormalizerTest extends TestCase
{
    private ZeltyOrderNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ZeltyOrderNormalizer();
    }

    public function testNormalizesBasicOrderPayload(): void
    {
        $shippedAt = new \DateTime('2026-07-01T12:00:00+02:00');

        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getFirstName')->willReturn('Jean');
        $customer->method('getLastName')->willReturn('Dupont');
        $customer->method('getEmail')->willReturn('jean@example.com');
        $customer->method('getTelephone')->willReturn('+33612345678');

        $address = $this->createMock(Address::class);
        $address->method('getName')->willReturn('Chez Jean');
        $address->method('getStreetAddress')->willReturn('12 rue de la Paix');
        $address->method('getPostalCode')->willReturn('75001');
        $address->method('getAddressLocality')->willReturn('Paris');

        $product = new Product();
        $product->setMetadata(['zelty_id' => 'ZD1269330', 'zelty_internal_id' => '1269330']);

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getCode')->willReturn('ZD1269330_variant');
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getOptionValues')->willReturn(new ArrayCollection());

        $item = $this->createMock(OrderItem::class);
        $item->method('getVariant')->willReturn($variant);
        $item->method('getUnitPrice')->willReturn(600);
        $item->method('getQuantity')->willReturn(1);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn(751);
        $order->method('getNumber')->willReturn('QF12345678');
        $order->method('getShippedAt')->willReturn($shippedAt);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getShippingAddress')->willReturn($address);
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));
        $order->method('getItemsTotal')->willReturn(600);
        $order->method('getNotes')->willReturn(null);

        $payload = $this->normalizer->normalize($order);

        $this->assertSame('751', $payload['remote_id']);
        $this->assertSame('QF12345678', $payload['display_id']);
        $this->assertSame('deliver_by_partner', $payload['fulfillment_type']);
        $this->assertSame('delivery', $payload['mode']);
        $this->assertSame('web', $payload['source']);
        $this->assertSame($shippedAt->format(\DateTime::ATOM), $payload['due_date']);
        $this->assertSame(600, $payload['total']);
        $this->assertArrayNotHasKey('comment', $payload);

        $this->assertSame([
            'remote_id' => '42',
            'fname'     => 'Jean',
            'name'      => 'Dupont',
            'mail'      => 'jean@example.com',
            'phone'     => '+33612345678',
        ], $payload['customer']);

        $this->assertSame([
            'name'     => 'Chez Jean',
            'street'   => '12 rue de la Paix',
            'zip_code' => '75001',
            'city'     => 'Paris',
        ], $payload['address']);

        $this->assertCount(1, $payload['items']);
        $this->assertSame([
            'id'        => '1269330',
            'remote_id' => 'ZD1269330_variant',
            'type'      => 'dish',
            'price'     => 600,
        ], $payload['items'][0]);
    }

    public function testItemWithQuantityIsRepeated(): void
    {
        $product = new Product();
        $product->setMetadata(['zelty_id' => 'ZD1983', 'zelty_internal_id' => '1983']);

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getCode')->willReturn('ZD1983_variant');
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getOptionValues')->willReturn(new ArrayCollection());

        $item = $this->createMock(OrderItem::class);
        $item->method('getVariant')->willReturn($variant);
        $item->method('getUnitPrice')->willReturn(1200);
        $item->method('getQuantity')->willReturn(3);

        $order = $this->buildMinimalOrder([$item], 3600);

        $payload = $this->normalizer->normalize($order);

        $this->assertCount(3, $payload['items']);
        foreach ($payload['items'] as $entry) {
            $this->assertSame('1983', $entry['id']);
            $this->assertSame(1200, $entry['price']);
        }
    }

    public function testItemWithModifiers(): void
    {
        $product = new Product();
        $product->setMetadata(['zelty_id' => 'ZD1976713', 'zelty_internal_id' => '1976713']);

        $optionValueWithZelty = new ProductOptionValue();
        $optionValueWithZelty->setMetadata(['zelty_id' => 'ZOV1403530', 'zelty_internal_id' => '1403530']);
        $optionValueWithZelty->setPrice(0);

        $optionValueWithoutZelty = new ProductOptionValue();

        $variant = $this->createMock(ProductVariant::class);
        $variant->method('getCode')->willReturn('ZD1976713_variant');
        $variant->method('getProduct')->willReturn($product);
        $variant->method('getOptionValues')->willReturn(
            new ArrayCollection([$optionValueWithZelty, $optionValueWithoutZelty])
        );

        $item = $this->createMock(OrderItem::class);
        $item->method('getVariant')->willReturn($variant);
        $item->method('getUnitPrice')->willReturn(1480);
        $item->method('getQuantity')->willReturn(1);

        $order = $this->buildMinimalOrder([$item], 1480);

        $payload = $this->normalizer->normalize($order);

        $this->assertCount(1, $payload['items']);
        $this->assertSame([
            ['id' => '1403530', 'price' => 0],
        ], $payload['items'][0]['modifiers']);
    }

    public function testNotesAreTruncatedTo256Characters(): void
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getVariant')->willReturn(null);
        $item->method('getUnitPrice')->willReturn(0);
        $item->method('getQuantity')->willReturn(1);

        $order = $this->buildMinimalOrder([$item], 0, str_repeat('a', 300));

        $payload = $this->normalizer->normalize($order);

        $this->assertSame(256, strlen($payload['comment']));
    }

    public function testSupportsNormalization(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->assertTrue($this->normalizer->supportsNormalization($order));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    private function buildMinimalOrder(array $items, int $total, ?string $notes = null): OrderInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getId')->willReturn(1);
        $order->method('getNumber')->willReturn('ABC123');
        $order->method('getShippedAt')->willReturn(null);
        $order->method('getCustomer')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getItems')->willReturn(new ArrayCollection($items));
        $order->method('getItemsTotal')->willReturn($total);
        $order->method('getNotes')->willReturn($notes);

        return $order;
    }
}
