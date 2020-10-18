<?php

namespace AppBundle\Twig;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Enum\FoodEstablishment;
use AppBundle\Enum\Store;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

class LocalBusinessRuntime implements RuntimeExtensionInterface
{
    public function __construct(TranslatorInterface $translator, SerializerInterface $serializer)
    {
        $this->translator = $translator;
        $this->serializer = $serializer;
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

    public function seo(LocalBusiness $entity): array
    {
        return $this->serializer->normalize($entity, 'jsonld', [
            'resource_class' => LocalBusiness::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['restaurant_seo', 'address']
        ]);
    }

    public function delayForHumans(LocalBusiness $restaurant, $locale): string
    {
        if ($restaurant->getOrderingDelayMinutes() > 0) {

            Carbon::setLocale($locale);

            $now = Carbon::now();
            $future = clone $now;
            $future->addMinutes($restaurant->getOrderingDelayMinutes());

            return $now->diffForHumans($future, ['syntax' => CarbonInterface::DIFF_ABSOLUTE]);
        }

        return '';
    }
}
