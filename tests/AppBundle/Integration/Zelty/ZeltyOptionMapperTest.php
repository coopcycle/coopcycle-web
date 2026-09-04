<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Integration\Zelty\Dto\ZeltyOption;
use AppBundle\Integration\Zelty\Dto\ZeltyOptionValue;
use AppBundle\Integration\Zelty\ZeltyOptionMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Reproduces a production failure: re-importing a catalog where the same Zelty
 * option value id is referenced by two different option groups blew up with
 * "duplicate key value violates unique constraint … Key (code)=(ZOVxxx_yyy)
 * already exists" — ProductOptionValue.code is globally unique, but the old
 * lookup only searched the option's own in-memory collection, so the second
 * option never saw the row the first one had already created.
 */
class ZeltyOptionMapperTest extends TestCase
{
    public function testOptionValueSharedByTwoOptionsIsNotInsertedTwice(): void
    {
        /** @var array<string, ProductOptionValue> $persistedValues */
        $persistedValues = [];

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            fn (array $criteria) => $persistedValues[$criteria['code']] ?? null
        );

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('getFilters')->willReturn($filters);
        $em->method('persist')->willReturnCallback(function ($entity) use (&$persistedValues) {
            if ($entity instanceof ProductOptionValue) {
                $persistedValues[$entity->getCode()] = $entity;
            }
        });

        $restaurant = new LocalBusiness();
        (new ReflectionProperty($restaurant, 'id'))->setValue($restaurant, 178);

        $sharedValue = new ZeltyOptionValue(id: 'ZOV214041', name: 'Sans oignons');
        $optionA = new ZeltyOption(id: 'ZOA', name: 'Garnitures burger', valueIds: ['ZOV214041']);
        $optionB = new ZeltyOption(id: 'ZOB', name: 'Garnitures tacos', valueIds: ['ZOV214041']);

        $mapper = new ZeltyOptionMapper($em);
        $optionMap = $mapper->importOptions([$optionA, $optionB], [$sharedValue], $restaurant, 'fr');

        // Only one row for the shared value: the second option must reuse it
        // rather than attempt a second INSERT with the same code.
        $this->assertCount(1, $persistedValues);
        $this->assertArrayHasKey('ZOV214041_178', $persistedValues);

        // ProductOptionValue->option is a required single FK: the value cannot
        // belong to both at once, so importing it under B reparents it away from
        // A. That's fine — what matters is exactly one row exists and it ends up
        // attached to whichever option last claimed it, with no duplicate insert.
        $value = $persistedValues['ZOV214041_178'];
        $this->assertSame($optionMap['ZOB'], $value->getOption());
        $this->assertTrue($optionMap['ZOB']->getValues()->contains($value));
        $this->assertFalse($optionMap['ZOA']->getValues()->contains($value));
    }
}
