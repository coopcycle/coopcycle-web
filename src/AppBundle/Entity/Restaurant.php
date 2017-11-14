<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Base\FoodEstablishment;
use AppBundle\Utils\TimeRange;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;
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
 *   },
 *   collectionOperations={
 *     "get"={"method"="GET"}
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"}
 *   }
 * )

 * @Vich\Uploadable
 */
class Restaurant extends FoodEstablishment
{
    /**
     *  Delay for preparation (in minutes)
     */
    const PREPARATION_DELAY = 30;

    /**
     *  Delay for delivery (in minutes)
     */
    const DELIVERY_DELAY = 15;

    /**
     *  Delay for preparation + delivery (in minutes)
     */
    const PREPARATION_AND_DELIVERY_DELAY = self::PREPARATION_DELAY + self::DELIVERY_DELAY;

    /**
     *  We allow ordering at J+1
     */
    const NUMBER_OF_AVAILABLE_DAYS = 2;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string The name of the item
     *
     * @Assert\Type(type="string")
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"restaurant", "order"})
     */
    protected $name;

    /**
     * @var string The cuisine of the restaurant.
     *
     * @ORM\ManyToMany(targetEntity="Cuisine", cascade={"persist"})
     * @ORM\JoinTable(inverseJoinColumns={@ORM\JoinColumn()})
     * @ORM\OrderBy({"name"="ASC"})
     * @ApiProperty(iri="https://schema.org/servesCuisine")
     * @Groups({"restaurant"})
     */
    protected $servesCuisine;

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
     * @ORM\OneToOne(targetEntity="Address", cascade={"all"})
     * @Groups({"restaurant"})
     */
    private $address;

    /**
     * @var string The website of the restaurant.
     *
     * @ORM\Column(nullable=true)
     * @ApiProperty(iri="https://schema.org/URL")
     */
    private $website;

    /**
     * @var string The delivery service of the restaurant.
     *
     * @ORM\OneToOne(targetEntity="DeliveryService", cascade={"persist"})
     * @ORM\JoinColumn(name="delivery_service_id", referencedColumnName="id")
     */
    private $deliveryService;

    /**
     * @var string The Stripe params of the restaurant.
     *
     * @ORM\ManyToOne(targetEntity="StripeParams")
     */
    private $stripeParams;

    /**
     * @var string The menu of the restaurant.
     *
     * @ORM\OneToOne(targetEntity="Menu", cascade={"all"})
     * @ORM\JoinColumn(name="menu_id")
     * @ApiProperty(iri="https://schema.org/Menu")
     * @Groups({"restaurant"})
     */
    private $hasMenu;

    private $maxDistance = 3000;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    public function __construct()
    {
        $this->servesCuisine = new ArrayCollection();
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
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

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress(Address $address)
    {
        $this->address = $address;

        return $this;
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

    /**
     * Get the next date the restaurant will be opened at.
     *
     * @param \DateTime|null $now
     * @return mixed
     */
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

    /**
     * Return potential delivery times for a restaurant.
     *
     * We allow ordering at J+1.
     *
     * @param \DateTime|null $now
     * @return array
     */
    public function getAvailabilities(\DateTime $now = null) {

        if (!$now) {
            $now = new \DateTime();
        }

        $now->modify('+'.self::PREPARATION_AND_DELIVERY_DELAY.' minutes');

        $nextOpeningDate = $this->getNextOpeningDate($now);

        if (is_null($nextOpeningDate)) {
            return [];
        }

        $date =  clone $nextOpeningDate;

        $availabilities = [$date->format(\DateTime::ATOM)];

        $currentDay = $date->format('Ymd');
        $dayCount = 1;

        while ($dayCount <= self::NUMBER_OF_AVAILABLE_DAYS) {

            $date->modify('+15 minutes');

            $nextOpeningDate = $this->getNextOpeningDate($date);

            if (is_null($nextOpeningDate)) {
                return $availabilities;
            }

            $nextOpenedDate = clone $nextOpeningDate;

            $day = $nextOpenedDate->format('Ymd');

            if ($day !== $currentDay) {
                $currentDay = $day;
                $dayCount++;
            }
            else {
                $date = $nextOpenedDate;
                $availabilities[] = $date->format(\DateTime::ATOM);
            }
        }

        return $availabilities;
    }

    public function setServesCuisine($servesCuisine)
    {
        $this->servesCuisine = $servesCuisine;

        return $this;
    }

    public function addServesCuisine($servesCuisine)
    {
        $this->servesCuisine->add($servesCuisine);

        return $this;
    }

    public function getServesCuisine()
    {
        return $this->servesCuisine;
    }

    public function getStripeParams()
    {
        return $this->stripeParams;
    }

    public function setStripeParams(StripeParams $stripeParams)
    {
        $this->stripeParams = $stripeParams;

        return $this;
    }

    public function getDeliveryService()
    {
        return $this->deliveryService;
    }

    public function setDeliveryService($deliveryService)
    {
        $this->deliveryService = $deliveryService;

        return $this;
    }

    public function getHasMenu()
    {
        return $this->getMenu();
    }

    public function getMenu()
    {
        if (!$this->hasMenu) {
            $this->hasMenu = new Menu();
        }

        return $this->hasMenu;
    }

    public function setMenu(Menu $menu)
    {
        $this->hasMenu = $menu;
    }

    public function getMaxDistance()
    {
        return $this->maxDistance;
    }

    public function setMaxDistance($maxDistance)
    {
        $this->maxDistance = $maxDistance;

        return $this;
    }
}
