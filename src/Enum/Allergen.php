<?php

namespace AppBundle\Enum;

use MyCLabs\Enum\Enum;

/**
 * @see https://www.fsai.ie/legislation/food_legislation/food_information/14_allergens.html
 */
class Allergen extends Enum
{
    /**
     * Cereals containing gluten, namely: wheat (such as spelt and khorasan wheat), rye, barley, oats or their hybridised strains, and products thereof, except:
     * (a) wheat based glucose syrups including dextrose
     * (b) wheat based maltodextrins
     * (c) glucose syrups based on barley
     * (d) cereals used for making alcoholic distillates including ethyl alcohol of agricultural origin
     */
    const CEREALS_CONTAINING_GLUTEN = 'CEREALS_CONTAINING_GLUTEN';

    const CRUSTACEANS = 'CRUSTACEANS';

    const EGGS = 'EGGS';

    const FISH = 'FISH';

    const PEANUTS = 'PEANUTS';

    const SOYBEANS = 'SOYBEANS';

    const MILK = 'MILK';

    const NUTS = 'NUTS';

    const CELERY = 'CELERY';

    const MUSTARD = 'MUSTARD';

    const SESAME_SEEDS = 'SESAME_SEEDS';

    const SULPHUR_DIOXIDE_SULPHITES = 'SULPHUR_DIOXIDE_SULPHITES';

    const LUPIN = 'LUPIN';

    const MOLLUSCS = 'MOLLUSCS';
}
