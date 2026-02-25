<?php

namespace AppBundle\Integration\Zelty\Dto;

class ZeltyItem
{
    public const TYPE_DISH = 'dish';
    public const TYPE_MENU = 'menu';

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $img = null,
        public readonly bool $disabled = false,
        public readonly bool $isSoldOut = false,
        public readonly ?ZeltyPrice $price = null,
        public readonly ?ZeltyTaxRule $taxRule = null,
        public readonly array $optionIds = [],
        public readonly array $parts = [],
    ) {}

    public function isDish(): bool
    {
        return $this->type === self::TYPE_DISH;
    }

    public function isMenu(): bool
    {
        return $this->type === self::TYPE_MENU;
    }
}
