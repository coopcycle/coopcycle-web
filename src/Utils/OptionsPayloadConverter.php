<?php

namespace AppBundle\Utils;

use Sylius\Component\Resource\Repository\RepositoryInterface;

class OptionsPayloadConverter
{
    public function __construct(private RepositoryInterface $productOptionValueRepository)
    {}

    public function convert($product, array $options): \SplObjectStorage
    {
        $optionValues = new \SplObjectStorage();

        foreach ($options as $option) {
            // Legacy
            if (is_string($option)) {
                $optionValue = $this->productOptionValueRepository->findOneByCode($option);
                $optionValues->attach($optionValue);
            } else {
                $optionValue = $this->productOptionValueRepository->findOneByCode($option['code']);
                if ($optionValue && $product->hasOptionValue($optionValue)) {
                    $quantity = isset($option['quantity']) ? (int) $option['quantity'] : 0;
                    $quantity = $this->getQuantity($optionValue, $quantity);
                    if ($quantity > 0) {
                        $optionValues->attach($optionValue, $quantity);
                    }
                }
            }
        }

        return $optionValues;
    }

    private function getQuantity($optionValue, int $default = 0): int
    {
        if (!$optionValue->getOption()->isAdditional()) {

            return 1;
        }

        return $default;
    }
}
