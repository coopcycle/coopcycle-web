<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use AppBundle\Action\MyStores;
use AppBundle\Entity\Base\LocalBusiness;
use AppBundle\Entity\Model\CustomFailureReasonInterface;
use AppBundle\Entity\Model\CustomFailureReasonTrait;
use AppBundle\Entity\Model\OrganizationAwareInterface;
use AppBundle\Entity\Model\OrganizationAwareTrait;
use AppBundle\Entity\Model\TaggableInterface;
use AppBundle\Entity\Model\TaggableTrait;
use AppBundle\Entity\Package;
use AppBundle\Entity\Task\RecurrenceRule;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use AppBundle\Action\TimeSlot\StoreTimeSlots as TimeSlots;
use AppBundle\Action\Store\Packages as Packages;

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
 *     },
 *     "delete"={
 *       "method"="DELETE",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     },
 *     "patch"={
 *       "method"="PATCH",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     },
 *     "time_slots"={
 *       "method"="GET",
 *       "path"="/stores/{id}/time_slots",
 *       "controller"=TimeSlots::class,
 *       "normalization_context"={"groups"={"store_time_slots"}},
 *       "security"="is_granted('edit', object)"
 *     },
 *     "packages"={
 *       "method"="GET",
 *       "path"="/stores/{id}/packages",
 *       "controller"=Packages::class,
 *       "normalization_context"={"groups"={"store_packages"}},
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
class Store extends LocalBusiness implements TaggableInterface, OrganizationAwareInterface, CustomFailureReasonInterface
{
    use SoftDeleteable;
    use TaggableTrait;
    use OrganizationAwareTrait;
    use CustomFailureReasonTrait;

    /**
     * @var int
     * @Groups({"store"})
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

    private $rrules;

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

    /**
     * @Groups({"store"})
     */
    private $weightRequired = false;

    /**
     * @Groups({"store"})
     */
    private $packagesRequired = false;

    private $multiDropEnabled = false;

    /**
     * @var Collection<int, StoreTimeSlot>
     * @Groups({"store"})
     */
    private $timeSlots;

    private ?string $transporter = null;

    /**
     * The deliveries of this store will be linked by default to this rider
     * @var User
    */
    private $defaultCourier;

    protected string $billingMethod = 'unit';

    public function __construct() {
        $this->deliveries = new ArrayCollection();
        $this->owners = new ArrayCollection();
        $this->addresses = new ArrayCollection();
        $this->timeSlots = new ArrayCollection();
        $this->rrules = new ArrayCollection();
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
    /**
     * @param mixed $website
     */
    public function setWebsite($website)
    {
        $this->website = $website;

        return $this;
    }
    /**
     * @param mixed $imageName
     */
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
    /**
     * @param mixed $pricingRuleSet
     */
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
    /**
     * @param mixed $prefillPickupAddress
     */
    public function setPrefillPickupAddress($prefillPickupAddress)
    {
        $this->prefillPickupAddress = $prefillPickupAddress;

        return $this;
    }

    public function getCreateOrders()
    {
        return $this->createOrders;
    }
    /**
     * @param mixed $createOrders
     */
    public function setCreateOrders($createOrders)
    {
        $this->createOrders = $createOrders;

        return $this;
    }

    public function getAddresses()
    {
        return $this->addresses;
    }
    /**
     * @param mixed $addresses
     */
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
    /**
     * @param mixed $timeSlot
     */
    public function setTimeSlot($timeSlot)
    {
        $this->timeSlot = $timeSlot;

        return $this;
    }

    public function getTimeSlot()
    {
        return $this->timeSlot;
    }
    /**
     * @param mixed $packageSet
     */
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
    /**
     * @param mixed $checkExpression
     */
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

    /**
     * @return bool
     */
    public function isMultiDropEnabled()
    {
        return $this->multiDropEnabled;
    }

    /**
     * @param bool $multiDropEnabled
     *
     * @return self
     */
    public function setMultiDropEnabled($multiDropEnabled)
    {
        $this->multiDropEnabled = $multiDropEnabled;

        return $this;
    }

    public function getTimeSlots()
    {
        return $this->timeSlots->map(fn (StoreTimeSlot $sts): TimeSlot => $sts->getTimeSlot());
    }

    public function setTimeSlots($timeSlots): void
    {
        $originalTimeSlots = new ArrayCollection();
        foreach ($this->timeSlots as $sts) {
            $originalTimeSlots->add($sts->getTimeSlot());
        }

        /** @var Collection<int, TimeSlot> */
        $newTimeSlots = new ArrayCollection();
        foreach ($timeSlots as $ts) {
            $newTimeSlots->add($ts);
        }

        /** @var TimeSlot[] */
        $timeSlotsToRemove = [];
        foreach ($originalTimeSlots as $originalTimeSlot) {
            if (!$newTimeSlots->contains($originalTimeSlot)) {
                $timeSlotsToRemove[] = $originalTimeSlot;
            }
        }

        foreach ($timeSlotsToRemove as $ts) {
            foreach ($this->timeSlots as $i => $sts) {
                if ($sts->getTimeSlot() === $ts) {
                    $this->timeSlots->remove($i);
                }
            }
        }

        foreach ($newTimeSlots as $position => $ts) {

            foreach ($this->timeSlots as $i => $sts) {
                if ($sts->getTimeSlot() === $ts) {
                    $sts->setPosition($position);
                    continue 2;
                }
            }

            $sts = new StoreTimeSlot();
            $sts->setStore($this);
            $sts->setTimeSlot($ts);
            $sts->setPosition($position);

            $this->timeSlots->add($sts);
        }
    }

    /**
     * @SerializedName("packages")
     * @Groups({"store_with_packages"})
     *
     * @return Package[]
     */
    public function getPackages()
    {
        if (null !== $this->packageSet) {
            return array_values($this->packageSet->getPackages()->toArray());
        }

        return [];
    }

   public function isTransporterEnabled(): bool
    {
        return !is_null($this->transporter);
    }

    public function getTransporter(): ?string
    {
        return $this->transporter;
    }

    public function setTransporter(?string $transporter): Store
    {
        $this->transporter = $transporter;
        return $this;
    }

    public function getDefaultCourier(): ?User
    {
        return $this->defaultCourier;
    }

    public function setDefaultCourier(?User $defaultCourier): Store
    {
        $this->defaultCourier = $defaultCourier;
        return $this;
    }

    /**
     * Get the recurrence rules linked to this store
     * @return RecurrenceRule[]
     */
    public function getRrules()
    {
        return $this->rrules;
    }

    public function setBillingMethod(string $billingMethod): void
    {
        $this->billingMethod = $billingMethod;
    }

    public function getBillingMethod(): string
    {
        return $this->billingMethod;
    }
}
