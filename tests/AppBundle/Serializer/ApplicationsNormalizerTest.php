<?php

namespace Tests\AppBundle\Serializer;

use AppBundle\Api\Dto\ResourceApplication;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Serializer\ApplicationsNormalizer;
use Hashids\Hashids;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class ApplicationsNormalizerTest extends TestCase
{
    private const SECRET = 'ThisIsNotSoSecret';

    private ApplicationsNormalizer $normalizer;

    public function setUp(): void
    {
        $this->normalizer = new ApplicationsNormalizer(new ObjectNormalizer(), self::SECRET);
    }

    private function createDeliveryForm(int $id, ?string $name = null): DeliveryForm
    {
        $deliveryForm = new DeliveryForm();
        $deliveryForm->setName($name);

        $property = new \ReflectionProperty($deliveryForm, 'id');
        $property->setAccessible(true);
        $property->setValue($deliveryForm, $id);

        return $deliveryForm;
    }

    public function testNormalizeNamedDeliveryForm()
    {
        $data = $this->normalizer->normalize(
            new ResourceApplication($this->createDeliveryForm(1, 'Bike delivery'))
        );

        $this->assertEquals([
            'entity' => DeliveryForm::class,
            'name' => 'Bike delivery',
            'id' => 1,
        ], $data);
    }

    /**
     * Forms created before they could be named are still identified by their hashid.
     */
    public function testNormalizeUnnamedDeliveryForm()
    {
        $hashids = new Hashids(self::SECRET, 12);

        foreach ([null, ''] as $name) {
            $data = $this->normalizer->normalize(
                new ResourceApplication($this->createDeliveryForm(1, $name))
            );

            $this->assertEquals([
                'entity' => DeliveryForm::class,
                'name' => $hashids->encode(1),
                'id' => 1,
            ], $data);
        }
    }
}
