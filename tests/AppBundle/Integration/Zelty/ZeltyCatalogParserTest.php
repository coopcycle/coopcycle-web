<?php

namespace Tests\AppBundle\Integration\Zelty;

use AppBundle\Integration\Zelty\Dto\ZeltyCatalogParser;
use AppBundle\Integration\Zelty\Dto\ZeltyItem;
use PHPUnit\Framework\TestCase;

class ZeltyCatalogParserTest extends TestCase
{
    private ZeltyCatalogParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ZeltyCatalogParser();
    }

    // -------------------------------------------------------------------------
    // Catalog metadata
    // -------------------------------------------------------------------------

    public function testCatalogMetadataIsParsed(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'       => 'CAT001',
            'name'     => 'Menu du jour',
            'locale'   => 'fr_FR',
            'currency' => 'EUR',
        ]));

        $this->assertSame('CAT001', $catalog->id);
        $this->assertSame('Menu du jour', $catalog->name);
        $this->assertSame('fr_FR', $catalog->locale);
        $this->assertSame('EUR', $catalog->currency);
    }

    public function testOptionalCatalogFieldsDefaultToNull(): void
    {
        $catalog = $this->parser->parse($this->payload(['id' => 'CAT001']));

        $this->assertNull($catalog->name);
        $this->assertNull($catalog->locale);
        $this->assertNull($catalog->currency);
    }

    // -------------------------------------------------------------------------
    // Dishes
    // -------------------------------------------------------------------------

    public function testBasicDishIsParsed(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                $this->dish('ZD1269330', 'Salade César', price: 1290, internalId: '1269330'),
            ],
        ]));

        $this->assertCount(1, $catalog->getDishes());
        $this->assertCount(0, $catalog->getMenus());

        $dish = array_values($catalog->getDishes())[0];
        $this->assertSame('ZD1269330', $dish->id);
        $this->assertSame('1269330', $dish->internalId);
        $this->assertSame('Salade César', $dish->name);
        $this->assertSame(1290, $dish->price->price);
        $this->assertFalse($dish->disabled);
        $this->assertFalse($dish->isSoldOut);
        $this->assertSame(ZeltyItem::TYPE_DISH, $dish->type);
    }

    public function testDisabledDishIsParsed(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                $this->dish('ZD1', 'Plat retiré', disabled: true),
            ],
        ]));

        $dish = array_values($catalog->getDishes())[0];
        $this->assertTrue($dish->disabled);
    }

    public function testSoldOutDishIsParsed(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                $this->dish('ZD1', 'Plat épuisé', isSoldOut: true),
            ],
        ]));

        $dish = array_values($catalog->getDishes())[0];
        $this->assertTrue($dish->isSoldOut);
    }

    public function testDishWithOptionIds(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                $this->dish('ZD1', 'Steak', optionIds: ['ZO276829_46', 'ZO276830_46']),
            ],
        ]));

        $dish = array_values($catalog->getDishes())[0];
        $this->assertSame(['ZO276829_46', 'ZO276830_46'], $dish->optionIds);
    }

    public function testDishWithTaxRule(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                array_merge($this->dish('ZD1', 'Dish'), ['tax_rules' => ['tax_id' => 'TAX_10']]),
            ],
        ]));

        $dish = array_values($catalog->getDishes())[0];
        $this->assertNotNull($dish->taxRule);
        $this->assertSame('TAX_10', $dish->taxRule->taxId);
    }

    // -------------------------------------------------------------------------
    // Menus
    // -------------------------------------------------------------------------

    public function testBasicMenuIsParsed(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                $this->menu('ZM87499', 'Formule Midi', price: 1290, internalId: '87499'),
            ],
        ]));

        $this->assertCount(0, $catalog->getDishes());
        $this->assertCount(1, $catalog->getMenus());

        $menu = array_values($catalog->getMenus())[0];
        $this->assertSame('ZM87499', $menu->id);
        $this->assertSame('87499', $menu->internalId);
        $this->assertSame('Formule Midi', $menu->name);
        $this->assertSame(ZeltyItem::TYPE_MENU, $menu->type);
        $this->assertSame(1290, $menu->price->price);
    }

    public function testMenuPartsAreExtracted(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                $this->menu('ZM87499', 'Formule', parts: ['ZMP141228', 'ZMP141229']),
            ],
        ]));

        $menu = array_values($catalog->getMenus())[0];
        $this->assertSame(['ZMP141228', 'ZMP141229'], $menu->parts);
    }

    public function testMenuWithNoPartsHasEmptyArray(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [$this->menu('ZM1', 'Menu vide')],
        ]));

        $menu = array_values($catalog->getMenus())[0];
        $this->assertSame([], $menu->parts);
    }

    // -------------------------------------------------------------------------
    // Menu parts
    // -------------------------------------------------------------------------

    public function testMenuPartsAreParsed(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'       => 'CAT001',
            'menuParts' => [
                ['id' => 'ZMP141228', 'internal_id' => '141228', 'name' => 'Entrée', 'dish_ids' => ['ZD1', 'ZD2']],
                ['id' => 'ZMP141229', 'internal_id' => '141229', 'name' => 'Plat',   'dish_ids' => ['ZD3', 'ZD4']],
            ],
        ]));

        $this->assertCount(2, $catalog->menuParts);

        $part1 = $catalog->menuParts[0];
        $this->assertSame('ZMP141228', $part1->id);
        $this->assertSame('141228', $part1->internalId);
        $this->assertSame('Entrée', $part1->name);
        $this->assertSame(['ZD1', 'ZD2'], $part1->dishIds);

        $part2 = $catalog->menuParts[1];
        $this->assertSame(['ZD3', 'ZD4'], $part2->dishIds);
    }

    // -------------------------------------------------------------------------
    // Options
    // -------------------------------------------------------------------------

    public function testOptionIsParsed(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'      => 'CAT001',
            'options' => [
                [
                    'id'              => 'ZO276829_46',
                    'internal_id'     => '276829',
                    'name'            => 'Sauce',
                    'value_ids'       => ['ZOV1', 'ZOV2'],
                    'minimum_choices' => 1,
                    'maximum_choices' => 1,
                ],
            ],
        ]));

        $this->assertCount(1, $catalog->options);

        $option = $catalog->options[0];
        $this->assertSame('ZO276829_46', $option->id);
        $this->assertSame('276829', $option->internalId);
        $this->assertSame('Sauce', $option->name);
        $this->assertSame(['ZOV1', 'ZOV2'], $option->valueIds);
        $this->assertSame(1, $option->min_choices);
        $this->assertSame(1, $option->max_choices);
    }

    public function testOptionDefaultChoices(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'      => 'CAT001',
            'options' => [['id' => 'ZO1', 'value_ids' => []]],
        ]));

        $option = $catalog->options[0];
        $this->assertSame(0, $option->min_choices);
        $this->assertSame(1, $option->max_choices);
    }

    // -------------------------------------------------------------------------
    // Option values
    // -------------------------------------------------------------------------

    public function testOptionValueIsParsed(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'           => 'CAT001',
            'optionValues' => [
                [
                    'id'          => 'ZOV1403530',
                    'internal_id' => '1403530',
                    'name'        => 'Vinaigrette',
                    'price'       => ['price' => 0, 'is_fixed' => true],
                    'disabled'    => false,
                    'is_sold_out' => false,
                ],
            ],
        ]));

        $this->assertCount(1, $catalog->optionValues);

        $value = $catalog->optionValues[0];
        $this->assertSame('ZOV1403530', $value->id);
        $this->assertSame('1403530', $value->internalId);
        $this->assertSame('Vinaigrette', $value->name);
        $this->assertSame(0, $value->price->price);
        $this->assertFalse($value->disabled);
        $this->assertFalse($value->isSoldOut);
    }

    public function testOptionValueWithExtraPrice(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'           => 'CAT001',
            'optionValues' => [
                [
                    'id'    => 'ZOV1',
                    'price' => ['price' => 200, 'is_fixed' => true],
                ],
            ],
        ]));

        $value = $catalog->optionValues[0];
        $this->assertSame(200, $value->price->price);
    }

    public function testDisabledOptionValue(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'           => 'CAT001',
            'optionValues' => [
                ['id' => 'ZOV1', 'disabled' => true, 'is_sold_out' => false],
            ],
        ]));

        $this->assertTrue($catalog->optionValues[0]->disabled);
    }

    // -------------------------------------------------------------------------
    // Complex: menu with dishes each having their own options (dependsOn scenario)
    // -------------------------------------------------------------------------

    public function testMenuWithDishesHavingDifferentOptions(): void
    {
        // Formule Midi: 2 parts
        //   Entrée: Salade César (has Sauce option) | Soupe du jour (no options)
        //   Plat:   Steak frites (has Cuisson option) | Poulet rôti (no options)
        $catalog = $this->parser->parse($this->payload([
            'id'        => 'CAT001',
            'items'     => [
                $this->menu('ZM87499', 'Formule Midi', price: 1490, parts: ['ZMP141228', 'ZMP141229']),
                $this->dish('ZD1', 'Salade César',  price: 0, optionIds: ['ZO_SAUCE']),
                $this->dish('ZD2', 'Soupe du jour', price: 0),
                $this->dish('ZD3', 'Steak frites',  price: 0, optionIds: ['ZO_CUISSON']),
                $this->dish('ZD4', 'Poulet rôti',   price: 0),
            ],
            'menuParts' => [
                ['id' => 'ZMP141228', 'name' => 'Entrée', 'dish_ids' => ['ZD1', 'ZD2']],
                ['id' => 'ZMP141229', 'name' => 'Plat',   'dish_ids' => ['ZD3', 'ZD4']],
            ],
            'options' => [
                ['id' => 'ZO_SAUCE',   'name' => 'Sauce',   'value_ids' => ['ZOV_VINAIGRETTE', 'ZOV_CESAR']],
                ['id' => 'ZO_CUISSON', 'name' => 'Cuisson', 'value_ids' => ['ZOV_BLEU', 'ZOV_SAIGNANT', 'ZOV_POINT']],
            ],
            'optionValues' => [
                ['id' => 'ZOV_VINAIGRETTE', 'name' => 'Vinaigrette', 'price' => ['price' => 0]],
                ['id' => 'ZOV_CESAR',       'name' => 'Caesar',      'price' => ['price' => 0]],
                ['id' => 'ZOV_BLEU',        'name' => 'Bleu',        'price' => ['price' => 0]],
                ['id' => 'ZOV_SAIGNANT',    'name' => 'Saignant',    'price' => ['price' => 0]],
                ['id' => 'ZOV_POINT',       'name' => 'À point',     'price' => ['price' => 0]],
            ],
        ]));

        // Catalog shape
        $this->assertCount(1, $catalog->getMenus());
        $this->assertCount(4, $catalog->getDishes());
        $this->assertCount(2, $catalog->menuParts);
        $this->assertCount(2, $catalog->options);
        $this->assertCount(5, $catalog->optionValues);

        // Menu has the 2 parts
        $menu = array_values($catalog->getMenus())[0];
        $this->assertSame(['ZMP141228', 'ZMP141229'], $menu->parts);

        // Parts carry the correct dish IDs
        $partMap = array_column($catalog->menuParts, null, 'id');
        $this->assertSame(['ZD1', 'ZD2'], $partMap['ZMP141228']->dishIds);
        $this->assertSame(['ZD3', 'ZD4'], $partMap['ZMP141229']->dishIds);

        // Dishes carry their option IDs
        $dishMap = array_column(iterator_to_array($catalog->getDishes()), null, 'id');
        $this->assertSame(['ZO_SAUCE'],   $dishMap['ZD1']->optionIds);
        $this->assertSame([],             $dishMap['ZD2']->optionIds);
        $this->assertSame(['ZO_CUISSON'], $dishMap['ZD3']->optionIds);
        $this->assertSame([],             $dishMap['ZD4']->optionIds);

        // Options carry the correct value IDs
        $optionMap = array_column($catalog->options, null, 'id');
        $this->assertSame(['ZOV_VINAIGRETTE', 'ZOV_CESAR'],              $optionMap['ZO_SAUCE']->valueIds);
        $this->assertSame(['ZOV_BLEU', 'ZOV_SAIGNANT', 'ZOV_POINT'],    $optionMap['ZO_CUISSON']->valueIds);
    }

    public function testMenuWithDisabledDishInPart(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                $this->menu('ZM1', 'Formule', parts: ['ZMP1']),
                $this->dish('ZD1', 'Actif'),
                $this->dish('ZD2', 'Retiré', disabled: true),
            ],
            'menuParts' => [
                ['id' => 'ZMP1', 'name' => 'Plat', 'dish_ids' => ['ZD1', 'ZD2']],
            ],
        ]));

        $dishMap = array_column(iterator_to_array($catalog->getDishes()), null, 'id');
        $this->assertFalse($dishMap['ZD1']->disabled);
        $this->assertTrue($dishMap['ZD2']->disabled);

        // Both dishes are still present in the part — filtering happens at import time, not parse time
        $this->assertSame(['ZD1', 'ZD2'], $catalog->menuParts[0]->dishIds);
    }

    public function testDishAndMenuAreCorrectlyDistinguished(): void
    {
        $catalog = $this->parser->parse($this->payload([
            'id'    => 'CAT001',
            'items' => [
                $this->dish('ZD1', 'Un plat'),
                $this->menu('ZM1', 'Un menu'),
                $this->dish('ZD2', 'Un autre plat'),
            ],
        ]));

        $this->assertCount(2, $catalog->getDishes());
        $this->assertCount(1, $catalog->getMenus());

        foreach ($catalog->getDishes() as $d) {
            $this->assertTrue($d->isDish());
            $this->assertFalse($d->isMenu());
        }
        foreach ($catalog->getMenus() as $m) {
            $this->assertTrue($m->isMenu());
            $this->assertFalse($m->isDish());
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function payload(array $data): array
    {
        return ['data' => array_merge(['id' => 'CAT001', 'items' => [], 'menuParts' => [], 'options' => [], 'optionValues' => [], 'tags' => []], $data)];
    }

    private function dish(
        string $id,
        string $name = '',
        int $price = 0,
        ?string $internalId = null,
        bool $disabled = false,
        bool $isSoldOut = false,
        array $optionIds = [],
    ): array {
        return [
            'id'          => $id,
            'internal_id' => $internalId ?? ltrim($id, 'ZD'),
            'type'        => 'dish',
            'name'        => $name,
            'disabled'    => $disabled,
            'is_sold_out' => $isSoldOut,
            'price'       => ['price' => $price, 'is_fixed' => true],
            'option_ids'  => $optionIds,
        ];
    }

    private function menu(
        string $id,
        string $name = '',
        int $price = 0,
        ?string $internalId = null,
        bool $disabled = false,
        array $parts = [],
    ): array {
        return [
            'id'          => $id,
            'internal_id' => $internalId ?? ltrim($id, 'ZM'),
            'type'        => 'menu',
            'name'        => $name,
            'disabled'    => $disabled,
            'is_sold_out' => false,
            'price'       => ['price' => $price, 'is_fixed' => true],
            'parts'       => array_map(fn(string $partId) => ['menu_part_id' => $partId], $parts),
        ];
    }
}
