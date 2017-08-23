<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

trait RecipeTrait
{
    /**
     * @var string Indicates a dietary restriction or guideline for which this recipe is suitable, e.g. diabetic, halal etc
     *
     * @Assert\Type(type="string")
     * @Assert\Choice(callback={"RestrictedDiet", "toArray"})
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="http://schema.org/suitableForDiet")
     * @Groups({"restaurant"})
     */
    protected $suitableForDiet;

    /**
     * @var string The category of the recipe—for example, appetizer, entree, etc
     *
     * @Assert\Type(type="string")
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="http://schema.org/recipeCategory")
     * @Groups({"restaurant"})
     */
    protected $recipeCategory;

    /**
     * @var string The cuisine of the recipe (for example, French or Ethiopian)
     *
     * @Assert\Type(type="string")
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="http://schema.org/recipeCuisine")
     * @Groups({"restaurant"})
     */
    protected $recipeCuisine;

    /**
     * Sets suitableForDiet.
     *
     * @param string $suitableForDiet
     *
     * @return $this
     */
    public function setSuitableForDiet($suitableForDiet)
    {
        $this->suitableForDiet = $suitableForDiet;

        return $this;
    }

    /**
     * Gets suitableForDiet.
     *
     * @return string
     */
    public function getSuitableForDiet()
    {
        return $this->suitableForDiet;
    }

    /**
     * Sets recipeCategory.
     *
     * @param string $recipeCategory
     *
     * @return $this
     */
    public function setRecipeCategory($recipeCategory)
    {
        $this->recipeCategory = $recipeCategory;

        return $this;
    }

    /**
     * Gets recipeCategory.
     *
     * @return string
     */
    public function getRecipeCategory()
    {
        return $this->recipeCategory;
    }

    /**
     * Sets recipeCuisine.
     *
     * @param string $recipeCuisine
     *
     * @return $this
     */
    public function setRecipeCuisine($recipeCuisine)
    {
        $this->recipeCuisine = $recipeCuisine;

        return $this;
    }

    /**
     * Gets recipeCuisine.
     *
     * @return string
     */
    public function getRecipeCuisine()
    {
        return $this->recipeCuisine;
    }
}
