<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use AppBundle\Action\MyStores;
use AppBundle\Entity\Base\LocalBusiness;
use AppBundle\Entity\Model\OrganizationAwareInterface;
use AppBundle\Entity\Model\OrganizationAwareTrait;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * A retail good store.
 *
 * @see http://schema.org/Store Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/Store",
 *   attributes={
 *     "normalization_context"={"groups"={"store", "address"}}
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_ADMIN')"
 *     },
 *     "me_stores"={
 *       "method"="GET",
 *       "path"="/me/stores",
 *       "controller"=MyStores::class
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('edit', object)"
 *     }
 *   },
 *   subresourceOperations={
 *     "deliveries_get_subresource"={
 *       "security"="is_granted('edit', object)"
 *     }
 *   }
 * )
 * @Vich\Uploadable
 */
class Store extends LocalBusiness implements TaggableInterface, OrganizationAwareInterface
{
    use TaggableTrait;
    use OrganizationAwareTrait;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string The name of the item
     *
     * @Assert\Type(type="string")
     * @ApiProperty(iri="http://schema.org/name")
     * @Groups({"store"})
     */
    protected $name;

    /**
     * @var boolean
     *
     * @Groups({"store"})
     */
    protected $enabled = false;

    /**
     * @Vich\UploadableField(mapping="store_image", fileNameProperty="imageName")
     * @Assert\File(
     *   maxSize = "1024k",
     *   mimeTypes = {"image/jpg", "image/jpeg", "image/png"}
     * )
     * @var File
     */
    private $imageFile;

    /**
     * @var string
     */
    private $imageName;

    /**
     * @Groups({"store"})
     */
    private $address;

    /**
     * @var string The website.
     *
     * @ApiProperty(iri="https://schema.org/URL")
     */
    private $website;

    /**
     * @var StripeAccount The Stripe account
     */
    private $stripeAccount;

    private $createdAt;

    private $updatedAt;

    private $pricingRuleSet;

    /**
     * @ApiSubresource
     */
    private $deliveries;

    private $owners;

    private $prefillPickupAddress = false;

    private $createOrders = false;

    /**
     * @ApiSubresource
     */
    private $addresses;

    /**
     * @Groups({"store"})
     */
    private $timeSlot;

    private $packageSet;

    private $checkExpression;

    private $weightRequired = false;

    private $packagesRequired = false;

    public function __construct() {
        $this->deliveries = new ArrayCollection();
        $this->owners = new ArrayCollection();
        $this->addresses = new ArrayCollection();
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

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled)
    {
        $this->enabled = $enabled;
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
     * @return Address|null
     */
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
     * @param File|UploadedFile|null $image
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

    public function getStripeAccount()
    {
        return $this->stripeAccount;
    }

    public function setStripeAccount(StripeAccount $stripeAccount)
    {
        $this->stripeAccount = $stripeAccount;

        return $this;
    }

    public function getPricingRuleSet()
    {
        return $this->pricingRuleSet;
    }

    public function setPricingRuleSet($pricingRuleSet)
    {
        $this->pricingRuleSet = $pricingRuleSet;

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getDeliveries()
    {
        return $this->deliveries;
    }

    public function addDelivery(Delivery $delivery)
    {
        $delivery->setStore($this);

        $this->deliveries->add($delivery);
    }

    public function getOwners()
    {
        return $this->owners;
    }

    public function getPrefillPickupAddress()
    {
        return $this->prefillPickupAddress;
    }

    public function setPrefillPickupAddress($prefillPickupAddress)
    {
        $this->prefillPickupAddress = $prefillPickupAddress;

        return $this;
    }

    public function getCreateOrders()
    {
        return $this->createOrders;
    }

    public function setCreateOrders($createOrders)
    {
        $this->createOrders = $createOrders;

        return $this;
    }

    public function getAddresses()
    {
        return $this->addresses;
    }

    public function setAddresses($addresses)
    {
        $this->addresses = $addresses;

        return $this;
    }

    public function addAddress(Address $address)
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
        }

        return $this;
    }

    public function setTimeSlot($timeSlot)
    {
        $this->timeSlot = $timeSlot;

        return $this;
    }

    public function getTimeSlot()
    {
        return $this->timeSlot;
    }

    public function setPackageSet($packageSet)
    {
        $this->packageSet = $packageSet;

        return $this;
    }

    public function getPackageSet()
    {
        return $this->packageSet;
    }

    public function createDelivery()
    {
        $delivery = Delivery::createWithDefaults();
        $delivery->setStore($this);

        if ($this->getPrefillPickupAddress()) {
            $defaultAddress = $this->getAddress();
            if ($defaultAddress) {
                $delivery->getPickup()->setAddress($defaultAddress);
            }
        }

        return $delivery;
    }

    public function setCheckExpression($checkExpression)
    {
        $this->checkExpression = $checkExpression;

        return $this;
    }

    public function getCheckExpression()
    {
        return $this->checkExpression;
    }

    /**
     * @return mixed
     */
    public function isWeightRequired()
    {
        return $this->weightRequired;
    }

    /**
     * @param mixed $weightRequired
     *
     * @return self
     */
    public function setWeightRequired($weightRequired)
    {
        $this->weightRequired = $weightRequired;

        return $this;
    }

    /**
     * @return mixed
     */
    public function isPackagesRequired()
    {
        return $this->packagesRequired;
    }

    /**
     * @param mixed $packagesRequired
     *
     * @return self
     */
    public function setPackagesRequired($packagesRequired)
    {
        $this->packagesRequired = $packagesRequired;

        return $this;
    }
}
