<?php

namespace AppBundle\Twig;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class LocalBusinessRuntime implements RuntimeExtensionInterface
{
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function type(LocalBusiness $entity): ?string
    {
        if (Store::isValid($entity->getType())) {
            foreach (Store::values() as $value) {
                if ($value->getValue() === $entity->getType()) {

                    return $this->translator->trans(sprintf('store.%s', $value->getKey()));
                }
            }
        }

        foreach (FoodEstablishment::values() as $value) {
            if ($value->getValue() === $entity->getType()) {

                return $this->translator->trans(sprintf('food_establishment.%s', $value->getKey()));
            }
        }

        return '';
    }
}
