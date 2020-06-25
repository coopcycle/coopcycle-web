<?php

namespace AppBundle\Sylius\Product;

use AppBundle\DataType\NumRange;
use Sylius\Component\Product\Model\ProductOptionInterface as BaseProductOptionInterface;

interface ProductOptionInterface extends BaseProductOptionInterface
{
    const STRATEGY_FREE = 'free';
    const STRATEGY_OPTION_VALUE = 'option_value';

    /**
     * @return string
     */
    public function getStrategy(): string;

    /**
     * @param string $strategy
     */
    public function setStrategy(string $strategy): void;

    /**
     * @param boolean $additional
     */
    public function setAdditional(bool $additional): void;

    /**
     * @return boolean
     */
    public function isAdditional(): bool;

    /**
     * @return NumRange
     */
    public function getValuesRange(): ?NumRange;
}
