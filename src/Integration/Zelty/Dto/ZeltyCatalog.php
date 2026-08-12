<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyCatalog
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $locale = null,
        public readonly ?string $currency = null,
        public readonly array $tags = [],
        public readonly array $items = [],
        public readonly array $menuParts = [],
        public readonly array $options = [],
        public readonly array $optionValues = [],
    ) {}

    public function getDishes(): array
    {
        return array_filter($this->items, fn(ZeltyItem $item) => $item->isDish());
    }

    public function getMenus(): array
    {
        return array_filter($this->items, fn(ZeltyItem $item) => $item->isMenu());
    }
}
