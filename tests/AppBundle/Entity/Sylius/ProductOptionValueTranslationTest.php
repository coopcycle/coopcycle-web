<?php

declare(strict_types=1);

namespace Tests\AppBundle\Entity\Sylius;

use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductOptionValueTranslationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    public function testValueLongerThan255Chars(): void
    {
        // Humanized name of a pricing rule chaining many out_zone conditions
        $zones = array_map(fn(int $i) => sprintf('Adresse dropoff hors zone "Zone %d"', $i), range(1, 10));
        $value = implode(', ', $zones).' - €1.00';
        $this->assertGreaterThan(255, mb_strlen($value));

        $option = new ProductOption();
        $option->setCode(Uuid::uuid4()->toString());
        $option->setCurrentLocale('en');
        $option->setName('Pricing rules');

        $optionValue = new ProductOptionValue();
        $optionValue->setCode(Uuid::uuid4()->toString());
        $optionValue->setCurrentLocale('en');
        $optionValue->setValue($value);

        $option->addValue($optionValue);

        $this->entityManager->persist($option);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var ProductOptionValue $reloaded */
        $reloaded = $this->entityManager->getRepository(ProductOptionValue::class)->find($optionValue->getId());
        $reloaded->setCurrentLocale('en');

        $this->assertEquals($value, $reloaded->getValue());
    }
}
