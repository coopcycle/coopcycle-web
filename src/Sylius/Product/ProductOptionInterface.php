<?php

namespace AppBundle\Sylius\Product;

use AppBundle\DataType\NumRange;
use Sylius\Component\Product\Model\ProductOptionInterface as BaseProductOptionInterface;

interface ProductOptionInterface extends BaseProductOptionInterface
{
    const STRATEGY_FREE = 'free';
    const STRATEGY_OPTION_VALUE = 'option_value';

    public function getStrategy(): string;

    public function setStrategy(string $strategy): void;

    public function setAdditional(bool $additional): void;

    public function isAdditional(): bool;

    /**
     * @return NumRange
     */
    public function getValuesRange(): ?NumRange;
}
