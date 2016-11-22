<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
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
 * @ORM\Table(
 *     options={"spatial_indexes"={"idx_restaurant_geo"}},
 *     indexes={
 *         @ORM\Index(name="idx_restaurant_geo", columns={"geo"}, flags={"spatial"})
 *     }
 * )
 */
class Restaurant extends FoodEstablishment
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="SEQUENCE")
     */
    private $id;

    /**
     * @var Recipe
     * @Groups({"restaurant"})
     * @ORM\ManyToMany(targetEntity="Product", cascade={"all"})
     * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn()})
     */
    private $products;

    public function __construct() {
        $this->products = new ArrayCollection();
    }

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

    public function addProduct(Product $product)
    {
        $this->products->add($product);

        return $this;
    }
}
