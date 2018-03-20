<?php

namespace AppBundle\Enum;

use ApiPlatform\Core\Annotation\ApiResource;
use MyCLabs\Enum\Enum;

/**
 * A diet restricted to certain foods or preparations for cultural, religious, health or lifestyle reasons.
 *
 * @see http://schema.org/RestrictedDiet Documentation on Schema.org
 * @ApiResource(iri="http://schema.org/RestrictedDiet")
 */
class RestrictedDiet extends Enum
{
    /**
     * @var string A diet appropriate for people with diabetes
     */
    const DIABETIC_DIET = 'http://schema.org/DiabeticDiet';

    /**
     * @var string A diet exclusive of gluten
     */
    const GLUTEN_FREE_DIET = 'http://schema.org/GlutenFreeDiet';

    /**
     * @var string A diet conforming to Islamic dietary practices
     */
    const HALAL_DIET = 'http://schema.org/HalalDiet';

    /**
     * @var string A diet conforming to Hindu dietary practices, in particular, beef-free
     */
    const HINDU_DIET = 'http://schema.org/HinduDiet';

    /**
     * @var string A diet conforming to Jewish dietary practices
     */
    const KOSHER_DIET = 'http://schema.org/KosherDiet';

    /**
     * @var string A diet focused on reduced calorie intake
     */
    const LOW_CALORIE_DIET = 'http://schema.org/LowCalorieDiet';

    /**
     * @var string A diet focused on reduced fat and cholesterol intake
     */
    const LOW_FAT_DIET = 'http://schema.org/LowFatDiet';

    /**
     * @var string A diet appropriate for people with lactose intolerance
     */
    const LOW_LACTOSE_DIET = 'http://schema.org/LowLactoseDiet';

    /**
     * @var string A diet focused on reduced sodium intake
     */
    const LOW_SALT_DIET = 'http://schema.org/LowSaltDiet';

    /**
     * @var string A diet exclusive of all animal products
     */
    const VEGAN_DIET = 'http://schema.org/VeganDiet';

    /**
     * @var string A diet exclusive of animal meat
     */
    const VEGETARIAN_DIET = 'http://schema.org/VegetarianDiet';

    /**
     * @var string The name of the item
     *
     * @Assert\Type(type="string")
     * @ApiProperty(iri="http://schema.org/name")
     */
    protected $name;

    /**
     * Sets name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
