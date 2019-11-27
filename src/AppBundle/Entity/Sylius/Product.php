<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\ReusablePackaging;
use AppBundle\Sylius\Product\ProductInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Sylius\Component\Product\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ApiResource(
 *   collectionOperations={
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "put"={
 *       "method"="PUT",
 *       "denormalization_context"={"groups"={"product_update"}},
 *       "access_control"="is_granted('ROLE_RESTAURANT') and null != object.getRestaurant() and user.ownsRestaurant(object.getRestaurant())"
 *     },
 *   },
 *   attributes={
 *     "normalization_context"={"groups"={"product"}}
 *   }
 * )
 * @Vich\Uploadable
 */
class Product extends BaseProduct implements ProductInterface
{
    protected $restaurant;

    /**
     * @Vich\UploadableField(mapping="product_image", fileNameProperty="imageName")
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

    protected $deletedAt;

    protected $reusablePackagingEnabled = false;

    protected $reusablePackagingUnit;

    protected $reusablePackaging;

    public function __construct()
    {
        parent::__construct();

        $this->restaurant = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant->get(0);
    }

    /**
     * {@inheritdoc}
     */
    public function setRestaurant(?Restaurant $restaurant): void
    {
        $this->restaurant->clear();
        $this->restaurant->add($restaurant);
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

    public function getAllergens()
    {
        $attributeValue = $this->getAttributeByCodeAndLocale('ALLERGENS', $this->currentLocale);
        if (null !== $attributeValue) {
            return $attributeValue->getValue();
        }

        return [];
    }

    public function getRestrictedDiets()
    {
        $attributeValue = $this->getAttributeByCodeAndLocale('RESTRICTED_DIETS', $this->currentLocale);
        if (null !== $attributeValue) {
            return $attributeValue->getValue();
        }

        return [];
    }

    public function hasNonAdditionalOptions()
    {
        foreach ($this->getOptions() as $option) {
            if (!$option->isAdditional()) {
                return true;
            }
        }

        return false;
    }

    public function hasOptionValue(ProductOptionValueInterface $optionValue): bool
    {
        return $this->hasOption($optionValue->getOption());
    }

    /**
     * @return mixed
     */
    public function isReusablePackagingEnabled()
    {
        return $this->reusablePackagingEnabled;
    }

    /**
     * @param mixed $reusablePackagingEnabled
     *
     * @return self
     */
    public function setReusablePackagingEnabled($reusablePackagingEnabled)
    {
        $this->reusablePackagingEnabled = $reusablePackagingEnabled;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getReusablePackagingUnit()
    {
        return $this->reusablePackagingUnit;
    }

    /**
     * @param mixed $reusablePackagingUnit
     *
     * @return self
     */
    public function setReusablePackagingUnit($reusablePackagingUnit)
    {
        $this->reusablePackagingUnit = $reusablePackagingUnit;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getReusablePackaging()
    {
        return $this->reusablePackaging;
    }

    /**
     * @param mixed $reusablePackaging
     *
     * @return self
     */
    public function setReusablePackaging(?ReusablePackaging $reusablePackaging)
    {
        if (null !== $reusablePackaging) {
            $restaurant = $this->getRestaurant();

            if (!$restaurant->hasReusablePackaging($reusablePackaging)) {
                throw new \LogicException(
                    sprintf('Product #%d belongs to restaurant #%d, but reusable packaging #%d is not associated to this restaurant',
                        $this->getId(), $restaurant->getId(), $reusablePackaging->getId())
                );
            }
        }

        $this->reusablePackaging = $reusablePackaging;

        return $this;
    }
}
