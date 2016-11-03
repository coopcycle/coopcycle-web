<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A restaurant.
 *
 * @see http://schema.org/Restaurant Documentation on Schema.org
 *
 * @ORM\Entity(repositoryClass="AppBundle\Entity\RestaurantRepository")
 * @ApiResource(iri="http://schema.org/Restaurant",
 *   attributes={
 *     "normalization_context"={"groups"={"restaurant", "place", "order"}}
 *   }
 * )
 */
class Restaurant extends FoodEstablishment
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Recipe
     * @Groups({"restaurant"})
     * @ORM\ManyToMany(targetEntity="Product")
     * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn()})
     */
    private $products;

    /**
     * Sets id.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets products.
     *
     * @param Product $products
     *
     * @return $this
     */
    public function setProducts(Product $products = null)
    {
        $this->products = $products;

        return $this;
    }

    /**
     * Gets products.
     *
     * @return Product
     */
    public function getProducts()
    {
        return $this->products;
    }
}
