<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\DataType\NumRange;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Integration\Zelty\Dto\ZeltyOption;
use AppBundle\Integration\Zelty\Dto\ZeltyOptionValue;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;

class ZeltyOptionMapper
{
    public function __construct(
        private EntityManagerInterface $em,
        /* private SlugifyInterface $slugify, */
    ) {}

    public function importOptions(
        array $options,
        array $optionValues,
        LocalBusiness $restaurant,
        string $locale
    ): array {
        $optionValueMap = [];

        foreach ($optionValues as $zeltyValue) {
            $optionValueMap[$zeltyValue->id] = $zeltyValue;
        }

        $optionMap = [];

        foreach ($options as $zeltyOption) {
            $option = $this->importOption($zeltyOption, $restaurant, $locale);
            $optionMap[$option->getCode()] = $option;
            $optionMap[$zeltyOption->id] = $option;

            foreach ($zeltyOption->valueIds as $valueId) {
                if (isset($optionValueMap[$valueId])) {
                    $this->importOptionValue($optionValueMap[$valueId], $option, $locale);
                }
            }
        }

        return $optionMap;
    }

    public function getOptionByCode(string $code): ?ProductOptionInterface
    {
        return $this->em->getRepository(ProductOption::class)->findOneBy(['code' => $code]);
    }

    private function importOption(ZeltyOption $zeltyOption, LocalBusiness $restaurant, string $locale): ProductOption
    {
        $optionCode = sprintf('%s_%d', $zeltyOption->id, $restaurant->getId());

        $option = $this->em->getRepository(ProductOption::class)->findOneBy([
            'code' => $optionCode,
            'restaurant' => $restaurant,
        ]);

        if (null === $option) {
            $option = new ProductOption();
            $option->setCode($optionCode);
            /* $option->setPosition(0); */
            $option->setRestaurant($restaurant);
            $option->setCurrentLocale($locale);
            $range = (new NumRange())
                ->setLower($zeltyOption->min_choices)
                ->setUpper($zeltyOption->max_choices);
            $option->setValuesRange($range);

            if ($zeltyOption->name) {
                $option->setName($zeltyOption->name);
            }

            $this->em->persist($option);
            $this->em->flush();
        }

        return $option;
    }

    private function importOptionValue(ZeltyOptionValue $zeltyValue, ProductOption $option, string $locale): ProductOptionValue
    {
        $valueCode = sprintf('%s_%d', $zeltyValue->id, $option->getRestaurant()->getId());

        $value = null;
        foreach ($option->getValues() as $existingValue) {
            if ($existingValue->getCode() === $valueCode) {
                $value = $existingValue;
                break;
            }
        }

        if (null === $value) {
            $value = new ProductOptionValue();
            if ($zeltyValue->price->price > 0) {
                $option->setStrategy(ProductOptionInterface::STRATEGY_OPTION_VALUE);
            }
            $value->setCode($valueCode);
            $value->setCurrentLocale($locale);
            $value->setValue($zeltyValue->name);
            $value->setOption($option);

            if ($zeltyValue->price) {
                $value->setPrice($zeltyValue->price->price);
            }

            $value->setEnabled(!$zeltyValue->disabled);

            $option->addValue($value);
            $this->em->persist($value);
            $this->em->flush();
        }

        return $value;
    }
}
