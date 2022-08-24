<?php

namespace AppBundle\Entity\Woopit;

use AppBundle\Entity\Store;
use AppBundle\Entity\Zone;
use Gedmo\Timestampable\Traits\Timestampable;

class WoopitIntegration
{
    use Timestampable;

    private $id;

    /**
     * @var string Name for the integration.
     */
    private $name;

    /**
     * @var float Max weight value in kg allowed for the integration.
     */
    private $maxWeight;

    /**
     * @var float Max length value in cm allowed for the integration.
     */
    private $maxLength;

    /**
     * @var float Max height value in cm allowed for the integration.
     */
    private $maxHeight;

    /**
     * @var float Max width value in cm allowed for the integration.
     */
    private $maxWidth;

    /**
     * @var Store|null Store associated to this integration.
     */
    private $store;

    /**
     * @var Zone|null Zone where this integration can be used for deliveries.
     */
    private $zone;

    /**
     * @var array Product types supported for the integration.
     */
    private $productTypes;

    /**
     * @var string Woopit Store identifier.
     */
    private $woopitStoreId;


    public function getId()
    {
        return $this->id;
    }

     /**
     * Gets maxWeight.
     *
     * @return float
     */
    public function getMaxWeight()
    {
        return $this->maxWeight;
    }

    /**
     * Sets maxWeight.
     *
     * @param float $weight
     *
     * @return $this
     */
    public function setMaxWeight($weight)
    {
        $this->maxWeight = $weight;

        return $this;
    }

    /**
     * Gets maxLength.
     *
     * @return float
     */
    public function getMaxLength()
    {
        return $this->maxLength;
    }

    /**
     * Sets maxLength.
     *
     * @param float $length
     *
     * @return $this
     */
    public function setMaxLength($length)
    {
        $this->maxLength = $length;

        return $this;
    }

    /**
     * Gets maxHeight.
     *
     * @return float
     */
    public function getMaxHeight()
    {
        return $this->maxHeight;
    }

    /**
     * Sets maxHeight.
     *
     * @param float $height
     *
     * @return $this
     */
    public function setMaxHeight($height)
    {
        $this->maxHeight = $height;

        return $this;
    }

    /**
     * Gets maxWidth.
     *
     * @return float
     */
    public function getMaxWidth()
    {
        return $this->maxWidth;
    }

    /**
     * Sets maxWidth.
     *
     * @param float $width
     *
     * @return $this
     */
    public function setMaxWidth($width)
    {
        $this->maxWidth = $width;

        return $this;
    }

    /**
     * Gets store.
     *
     * @return Store
     */
    public function getStore(): ?Store
    {
        return $this->store;
    }

    /**
     * Sets store.
     *
     * @param Store $store
     *
     * @return $this
     */
    public function setStore(Store $store)
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Gets zone.
     *
     * @return Zone
     */
    public function getZone(): ?Zone
    {
        return $this->zone;
    }

    /**
     * Sets zone.
     *
     * @param Zone $zone
     *
     * @return $this
     */
    public function setZone(Zone $zone)
    {
        $this->zone = $zone;

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
     * Gets productTypes.
     *
     * @return array
     */
    public function getProductTypes()
    {
        return $this->productTypes;
    }

    /**
     * Sets productTypes.
     *
     * @param array $productTypes
     *
     * @return $this
     */
    public function setProductTypes($productTypes)
    {
        $this->productTypes = $productTypes;

        return $this;
    }

    /**
     * Gets woopitStoreId.
     *
     * @return string
     */
    public function getWoopitStoreId()
    {
        return $this->woopitStoreId;
    }

    /**
     * Sets woopitStoreId.
     *
     * @param string $woopitStoreId
     *
     * @return $this
     */
    public function setWoopitStoreId($woopitStoreId)
    {
        $this->woopitStoreId = $woopitStoreId;

        return $this;
    }

    public static function allProductTypes()
    {
        return [
            'TYPOLOGY_DANGEROUS',
            'TYPOLOGY_FRAGILE',
            'TYPOLOGY_FRESH',
            'TYPOLOGY_FROZEN',
            'TYPOLOGY_GENERIC',
            'TYPOLOGY_GROCERY',
            'TYPOLOGY_SMALL_HOUSEHOLD',
            'TYPOLOGY_HOUSEHOLD',
            'TYPOLOGY_LARGE_HOUSEHOLD',
            'TYPOLOGY_VOLUMINOUS',
            'TYPOLOGY_VOLUMINOUS_FRAGILE',
            'TYPOLOGY_PALLET_GENERIC',
            'TYPOLOGY_NON_STANDARD',
        ];
    }

}
