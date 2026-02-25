<?php

namespace AppBundle\Integration\Zelty;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductOption;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Integration\Zelty\Dto\ZeltyOption;
use AppBundle\Integration\Zelty\Dto\ZeltyOptionValue;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;

class ZeltyOptionMapper
{
    public function __construct(
        private EntityManagerInterface $em,
        private SlugifyInterface $slugify,
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
            $option = $this->importOption($zeltyOption, $restaurant);
            $optionMap[$option->getCode()] = $option;

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

    private function importOption(ZeltyOption $zeltyOption, LocalBusiness $restaurant): ProductOption
    {
        $option = $this->em->getRepository(ProductOption::class)->findOneBy([
            'code' => $zeltyOption->id,
            'restaurant' => $restaurant,
        ]);

        if (null === $option) {
            $option = new ProductOption();
            $option->setCode($zeltyOption->id);
            $option->setPosition(0);
            $option->setRestaurant($restaurant);

            $this->em->persist($option);
            $this->em->flush();
        }

        return $option;
    }

    private function importOptionValue(ZeltyOptionValue $zeltyValue, ProductOption $option, string $locale): ProductOptionValue
    {
        $value = null;
        foreach ($option->getValues() as $existingValue) {
            if ($existingValue->getCode() === $zeltyValue->id) {
                $value = $existingValue;
                break;
            }
        }

        if (null === $value) {
            $value = new ProductOptionValue();
            $value->setCode($zeltyValue->id);
            $value->setCurrentLocale($locale);
            $value->setValue($this->slugify->slugify($zeltyValue->name ?? $zeltyValue->id));
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
