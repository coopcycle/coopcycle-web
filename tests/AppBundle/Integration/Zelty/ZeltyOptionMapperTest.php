<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\DataType\NumRange;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductOption;
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

    /**
     * Reproduces a production bug: an option group Zelty says is optional
     * (minimum_choices: 0, maximum_choices: 5 — "pick up to 5 extra sauces")
     * was showing on the site as mandatory ("Vous devez sélectionner au
     * moins 3 option(s)"), forcing customers into paid choices.
     *
     * Two layers were broken, both now fixed:
     *  - importOption() returned the existing ProductOption as-is once
     *    found, so valuesRange was only ever applied at creation, never
     *    refreshed on a later sync.
     *  - ProductOption::additional was never set at all (stuck at its
     *    entity default, false). The frontend's isMandatory()/isValid()
     *    (js/app/restaurant/components/ProductDetails/useProductOptions.js)
     *    only reads valuesRange when additional=true; when false, a
     *    selection is *always* required regardless of min/max. So even a
     *    correct valuesRange was ignored until additional was set too.
     */
    public function testReimportRefreshesMinAndMaxChoicesOnAnExistingOption(): void
    {
        $restaurant = new LocalBusiness();
        (new ReflectionProperty($restaurant, 'id'))->setValue($restaurant, 178);

        $existingOption = new ProductOption();
        $existingOption->setCode('ZO232512_178');
        $existingOption->setRestaurant($restaurant);
        $existingOption->setFallbackLocale('fr');
        $existingOption->setCurrentLocale('fr');
        // Stale state: previously synced (or manually set) as mandatory,
        // min 3 — exactly what the production screenshot showed.
        $existingOption->setValuesRange((new NumRange())->setLower(3)->setUpper(3));

        $optionRepository = $this->createMock(ObjectRepository::class);
        $optionRepository->method('findOneBy')->willReturn($existingOption);

        $valueRepository = $this->createMock(ObjectRepository::class);
        $valueRepository->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => $class === ProductOption::class ? $optionRepository : $valueRepository
        );

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);
        $em->method('getFilters')->willReturn($filters);

        // Zelty's current, correct config for "Sauce supplémentaire frites":
        // optional (min 0), up to 5 picks.
        $zeltyOption = new ZeltyOption(
            id: 'ZO232512',
            name: 'Sauce supplémentaire frites',
            valueIds: [],
            min_choices: 0,
            max_choices: 5,
        );

        $mapper = new ZeltyOptionMapper($em);
        $optionMap = $mapper->importOptions([$zeltyOption], [], $restaurant, 'fr');

        $range = $optionMap['ZO232512']->getValuesRange();
        $this->assertSame(0, $range->getLower(), 'A re-import must clear a stale mandatory minimum.');
        $this->assertSame(5, $range->getUpper());
        $this->assertTrue(
            $optionMap['ZO232512']->isAdditional(),
            'A multi-pick option must go through the "additional" code path or the frontend ignores valuesRange entirely.'
        );
    }

    /**
     * A genuinely mandatory single pick (min 1, max 1 — e.g. "choose your
     * bread") is the one case the plain radio UI's hardcoded "always
     * required" already matches Zelty's own semantics exactly. This must
     * stay additional=false so it keeps rendering as a radio group instead
     * of switching to the quantity-stepper UI.
     */
    public function testMandatorySingleChoiceOptionStaysNonAdditional(): void
    {
        $restaurant = new LocalBusiness();
        (new ReflectionProperty($restaurant, 'id'))->setValue($restaurant, 178);

        $repository = $this->createMock(ObjectRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('getFilters')->willReturn($filters);

        $zeltyOption = new ZeltyOption(
            id: 'ZO_BREAD',
            name: 'Choix du pain',
            valueIds: [],
            min_choices: 1,
            max_choices: 1,
        );

        $mapper = new ZeltyOptionMapper($em);
        $optionMap = $mapper->importOptions([$zeltyOption], [], $restaurant, 'fr');

        $this->assertFalse($optionMap['ZO_BREAD']->isAdditional());
    }

    /**
     * The same Zelty option id can offer a different set of values per
     * catalog — "Choix des frites" has "Frites au cheddar" in the Click and
     * Collect catalog but not in Naofood's. Nothing used to revisit a value
     * dropped from an option's value_ids, so it stayed enabled forever, no
     * longer reflecting what the option actually offers. A value still
     * listed must stay untouched; one no longer listed must be disabled.
     */
    public function testValueRemovedFromAnOptionInZeltyIsDisabled(): void
    {
        $restaurant = new LocalBusiness();
        (new ReflectionProperty($restaurant, 'id'))->setValue($restaurant, 178);

        $existingOption = new ProductOption();
        $existingOption->setCode('ZO267689_178');
        $existingOption->setRestaurant($restaurant);
        $existingOption->setFallbackLocale('fr');
        $existingOption->setCurrentLocale('fr');

        $kept = new ProductOptionValue();
        $kept->setCode('ZOV1356900_178');
        $kept->setZeltyId('ZOV1356900');
        $kept->setFallbackLocale('fr');
        $kept->setCurrentLocale('fr');
        $kept->setValue('Frites au parmesan');
        $kept->setEnabled(true);
        $existingOption->addValue($kept);

        $stale = new ProductOptionValue();
        $stale->setCode('ZOV1356901_178');
        $stale->setZeltyId('ZOV1356901');
        $stale->setFallbackLocale('fr');
        $stale->setCurrentLocale('fr');
        $stale->setValue('Frites au cheddar');
        $stale->setEnabled(true);
        $existingOption->addValue($stale);

        $optionRepository = $this->createMock(ObjectRepository::class);
        $optionRepository->method('findOneBy')->willReturn($existingOption);

        $valueRepository = $this->createMock(ObjectRepository::class);
        $valueRepository->method('findOneBy')->willReturn($kept);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => $class === ProductOption::class ? $optionRepository : $valueRepository
        );

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);
        $em->method('getFilters')->willReturn($filters);

        // Naofood's current "Choix des frites": only parmesan and épices —
        // cheddar isn't in this catalog's value_ids at all.
        $zeltyOption = new ZeltyOption(
            id: 'ZO267689',
            name: 'Choix des frites',
            valueIds: ['ZOV1356900'],
        );
        $zeltyValue = new ZeltyOptionValue(id: 'ZOV1356900', name: 'Frites au parmesan');

        $mapper = new ZeltyOptionMapper($em);
        $mapper->importOptions([$zeltyOption], [$zeltyValue], $restaurant, 'fr');

        $this->assertTrue($kept->isEnabled(), 'A value still offered by the option must stay untouched.');
        $this->assertFalse($stale->isEnabled(), 'A value no longer offered by the option must be disabled.');
    }

    /**
     * Nothing used to re-enable an existing value, so a choice switched off by
     * accident stayed off through every later import. Restaurant 82 hit this:
     * its pizza sizes were wrongly linked to the dish "Niçoise", and
     * DisabledProductListener disabled them along with it. Zelty is the source
     * of truth for whether a choice is offered, so re-importing restores it.
     */
    public function testReimportRestoresAValueZeltyStillOffers(): void
    {
        $restaurant = new LocalBusiness();
        (new ReflectionProperty($restaurant, 'id'))->setValue($restaurant, 82);

        $existingOption = new ProductOption();
        $existingOption->setCode('ZO1234_82');
        $existingOption->setRestaurant($restaurant);
        $existingOption->setFallbackLocale('fr');
        $existingOption->setCurrentLocale('fr');

        $disabled = new ProductOptionValue();
        $disabled->setCode('ZOV1_82');
        $disabled->setZeltyId('ZOV1');
        $disabled->setFallbackLocale('fr');
        $disabled->setCurrentLocale('fr');
        $disabled->setValue('Classique (31cm)');
        $disabled->setEnabled(false);
        $existingOption->addValue($disabled);

        $optionRepository = $this->createMock(ObjectRepository::class);
        $optionRepository->method('findOneBy')->willReturn($existingOption);

        $valueRepository = $this->createMock(ObjectRepository::class);
        $valueRepository->method('findOneBy')->willReturn($disabled);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => $class === ProductOption::class ? $optionRepository : $valueRepository
        );

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);
        $em->method('getFilters')->willReturn($filters);

        $zeltyOption = new ZeltyOption(
            id: 'ZO1234',
            name: 'Taille pizza',
            valueIds: ['ZOV1'],
        );
        $zeltyValue = new ZeltyOptionValue(id: 'ZOV1', name: 'Classique (31cm)');

        $mapper = new ZeltyOptionMapper($em);
        $mapper->importOptions([$zeltyOption], [$zeltyValue], $restaurant, 'fr');

        $this->assertTrue($disabled->isEnabled(), 'A choice Zelty still offers must come back on re-import.');
    }

    public function testReimportKeepsAValueZeltyItselfDisabled(): void
    {
        $restaurant = new LocalBusiness();
        (new ReflectionProperty($restaurant, 'id'))->setValue($restaurant, 82);

        $existingOption = new ProductOption();
        $existingOption->setCode('ZO1234_82');
        $existingOption->setRestaurant($restaurant);
        $existingOption->setFallbackLocale('fr');
        $existingOption->setCurrentLocale('fr');

        $value = new ProductOptionValue();
        $value->setCode('ZOV1_82');
        $value->setZeltyId('ZOV1');
        $value->setFallbackLocale('fr');
        $value->setCurrentLocale('fr');
        $value->setValue('Classique (31cm)');
        $value->setEnabled(true);
        $existingOption->addValue($value);

        $optionRepository = $this->createMock(ObjectRepository::class);
        $optionRepository->method('findOneBy')->willReturn($existingOption);

        $valueRepository = $this->createMock(ObjectRepository::class);
        $valueRepository->method('findOneBy')->willReturn($value);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            fn (string $class) => $class === ProductOption::class ? $optionRepository : $valueRepository
        );

        $filters = $this->createMock(FilterCollection::class);
        $filters->method('isEnabled')->willReturn(false);
        $em->method('getFilters')->willReturn($filters);

        $zeltyOption = new ZeltyOption(
            id: 'ZO1234',
            name: 'Taille pizza',
            valueIds: ['ZOV1'],
        );
        $zeltyValue = new ZeltyOptionValue(id: 'ZOV1', name: 'Classique (31cm)', disabled: true);

        $mapper = new ZeltyOptionMapper($em);
        $mapper->importOptions([$zeltyOption], [$zeltyValue], $restaurant, 'fr');

        $this->assertFalse($value->isEnabled(), 'A choice disabled in Zelty must stay off.');
    }
}
