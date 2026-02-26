<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyCatalogParser
{
    public function parse(array $payload): ZeltyCatalog
    {
        $data = $payload['data'];

        $tags = array_map(fn(array $tag) => $this->parseTag($tag), $data['tags'] ?? []);
        
        $items = array_map(fn(array $item) => $this->parseItem($item), $data['items'] ?? []);
        
        $menuParts = array_map(fn(array $part) => $this->parseMenuPart($part), $data['menuParts'] ?? []);
        
        $options = array_map(fn(array $option) => $this->parseOption($option), $data['options'] ?? []);
        
        $optionValues = array_map(fn(array $value) => $this->parseOptionValue($value), $data['optionValues'] ?? []);

        return new ZeltyCatalog(
            id: $data['id'],
            name: $data['name'] ?? null,
            locale: $data['locale'] ?? null,
            currency: $data['currency'] ?? null,
            tags: $tags,
            items: $items,
            menuParts: $menuParts,
            options: $options,
            optionValues: $optionValues,
        );
    }

    private function parseTag(array $data): ZeltyTag
    {
        return new ZeltyTag(
            id: $data['id'],
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            img: $data['image'] ?? null,
            color: $data['color'] ?? null,
            disabled: $data['disabled'] ?? false,
            itemIds: $data['item_ids'] ?? [],
        );
    }

    private function parseItem(array $data): ZeltyItem
    {
        $price = isset($data['price']) ? $this->parsePrice($data['price']) : null;
        $taxRule = isset($data['tax_rules']) ? $this->parseTaxRule($data['tax_rules']) : null;

        $parts = [];
        if ($data['type'] === 'menu' && !empty($data['parts'])) {
            $parts = array_map(fn(array $part) => $part['menu_part_id'], $data['parts']);
        }

        return new ZeltyItem(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            img: $data['img'] ?? null,
            disabled: $data['disabled'] ?? false,
            isSoldOut: $data['is_sold_out'] ?? false,
            price: $price,
            taxRule: $taxRule,
            optionIds: $data['option_ids'] ?? [],
            parts: $parts,
        );
    }

    private function parseMenuPart(array $data): ZeltyMenuPart
    {
        return new ZeltyMenuPart(
            id: $data['id'],
            name: $data['name'] ?? null,
            dishIds: $data['dish_ids'] ?? [],
        );
    }

    private function parseOption(array $data): ZeltyOption
    {
        return new ZeltyOption(
            id: $data['id'],
            name: $data['name'] ?? null,
            valueIds: $data['value_ids'] ?? [],
        );
    }

    private function parseOptionValue(array $data): ZeltyOptionValue
    {
        return new ZeltyOptionValue(
            id: $data['id'],
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            img: $data['img'] ?? null,
            disabled: $data['disabled'] ?? false,
            isSoldOut: $data['is_sold_out'] ?? false,
            price: isset($data['price']) ? $this->parsePrice($data['price']) : null,
            availability: isset($data['availability']) ? $this->parseAvailability($data['availability']) : null,
        );
    }

    private function parsePrice(array $data): ZeltyPrice
    {
        return new ZeltyPrice(
            price: (int) ($data['price'] ?? 0),
            isFixed: $data['is_fixed'] ?? true,
            preventDiscounts: $data['prevent_discounts'] ?? false,
        );
    }

    private function parseTaxRule(array $data): ZeltyTaxRule
    {
        return new ZeltyTaxRule(
            taxId: $data['tax_id'] ?? '',
        );
    }

    private function parseAvailability(array $data): ZeltyAvailability
    {
        return new ZeltyAvailability(
            startDate: $data['start_date'] ?? null,
            endDate: $data['end_date'] ?? null,
        );
    }
}
