<?php

namespace AppBundle\Entity;

use AppBundle\Utils\TimeRange;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * A restaurant.
 *
 * @see http://schema.org/Restaurant Documentation on Schema.org
 *
 * @ORM\Entity(repositoryClass="AppBundle\Entity\RestaurantRepository")
 * @ApiResource(iri="http://schema.org/Restaurant",
 *   attributes={
 *     "filters"={"restaurant.search"},
 *     "normalization_context"={"groups"={"restaurant", "place", "order"}}
 *   }
 * )
 * @ORM\Table(
 *     options={"spatial_indexes"={"idx_restaurant_geo"}},
 *     indexes={
 *         @ORM\Index(name="idx_restaurant_geo", columns={"geo"}, flags={"spatial"})
 *     }
 * )
 * @Vich\Uploadable
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

    /**
     * @Vich\UploadableField(mapping="restaurant_image", fileNameProperty="imageName")
     * @Assert\File(maxSize = "1024k")
     * @var File
     */
    private $imageFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @var string
     */
    private $imageName;

    /**
     * @var string The website of the restaurant.
     *
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="https://schema.org/URL")
     */
    private $website;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        parent::__construct();
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

    /**
     * Gets products.
     *
     * @return Product
     */
    public function getProductsByCategory($recipeCategory)
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->eq("recipe_category", $recipeCategory));

        return $this->products->matching($criteria);
    }

    public function addProduct(Product $product)
    {
        $this->products->add($product);

        return $this;
    }

    public function removeProduct(Product $product)
    {
        $this->products->removeElement($product);

        return $this;
    }

    public function getWebsite()
    {
        return $this->website;
    }

    public function setWebsite($website)
    {
        $this->website = $website;

        return $this;
    }

    public function setImageName($imageName)
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getImageName()
    {
        return $this->imageName;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|Symfony\Component\HttpFoundation\File\UploadedFile $image
     */
    public function setImageFile(File $image = null)
    {
        $this->imageFile = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    /**
     * @return File|null
     */
    public function getImageFile()
    {
        return $this->imageFile;
    }

    /**
     * @return boolean
     */
    public function isOpen(\DateTime $now = null)
    {
        if (!$now) {
            $now = new \DateTime();
        }

        foreach ($this->openingHours as $openingHour) {
            $timeRange = new TimeRange($openingHour);
            if ($timeRange->isOpen($now)) {
                return true;
            }
        }

        return false;
    }

    public function getNextOpeningDate(\DateTime $now = null)
    {
        if (!$now) {
            $now = new \DateTime();
        }

        $dates = [];
        foreach ($this->openingHours as $openingHour) {
            $timeRange = new TimeRange($openingHour);
            $dates[] = $timeRange->getNextOpeningDate($now);
        }

        sort($dates);

        return array_shift($dates);
    }
}
