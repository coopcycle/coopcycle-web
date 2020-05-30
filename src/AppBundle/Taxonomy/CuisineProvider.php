<?php

namespace AppBundle\Taxonomy;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * https://en.wikipedia.org/wiki/List_of_cuisines
 * http://developer-tripadvisor.com/partner/json-api/business-content/cuisines/
 * https://github.com/Factual/places/blob/master/restaurants/factual_cuisines.json
 */
class CuisineProvider
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function getSlugs(): array
    {
        if ($this->translator instanceof TranslatorBagInterface) {
            $catalogue = $this->translator->getCatalogue('en');
            return array_keys($catalogue->all('cuisines'));
        }

        return [];
    }
}
