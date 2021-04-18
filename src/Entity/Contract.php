<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Delivery\PricingRuleSet;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a contract between a restaurant and a coop
 */
class Contract
{
    private $id;

    /**
     * @var int
     * The amount (in cents) charged by the platform.
     * @Assert\NotBlank
     * @Assert\Type("integer")
     */
    private $flatDeliveryPrice = 0;

    /**
     * @var bool
     * Use a PricingRuleSet to calculate the amount charged by the platform.
     */
    private $variableDeliveryPriceEnabled = false;

    /**
     * @var PricingRuleSet|null
     * The pricing rule to calculate the amount charged by the platform.
     * @Assert\Expression(
     *   "this.getVariableDeliveryPrice() != null or !this.isVariableDeliveryPriceEnabled()",
     *   message="restaurant.contract.variableDeliveryPrice.pickOne",
     *   groups={"Default", "activable"}
     * )
     */
    private $variableDeliveryPrice;

    /**
     * @var int
     * The amount (in cents) paid by the customer.
     * @Assert\NotBlank
     * @Assert\Type("integer")
     */
    private $customerAmount = 0;

    /**
     * @var PricingRuleSet|null
     * The pricing rule to calculate the amount paid by the customer.
     * @Assert\Expression(
     *   "this.getVariableCustomerAmount() != null or !this.isVariableCustomerAmountEnabled()",
     *   message="restaurant.contract.variableCustomerAmount.pickOne",
     *   groups={"Default", "activable"}
     * )
     */
    private $variableCustomerAmount;

    /**
     * @var bool
     * Use a PricingRuleSet to calculate the amount paid by the customer.
     */
    private $variableCustomerAmountEnabled = false;

    /**
     * @var float
     * @Assert\NotBlank
     * @Assert\Type("float")
     */
    private $feeRate = 0.00;

    /**
     * @var bool
     * Restaurant pays Stripe fee?
     * @Assert\Type("bool")
     */
    private $restaurantPaysStripeFee = true;

    /**
     * @var float
     * @Assert\NotBlank
     * @Assert\Type("float")
     */
    private $takeAwayFeeRate = 0.00;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getFlatDeliveryPrice()
    {
        return $this->flatDeliveryPrice;
    }

    /**
     * @param int $flatDeliveryPrice
     */
    public function setFlatDeliveryPrice(int $flatDeliveryPrice)
    {
        $this->flatDeliveryPrice = $flatDeliveryPrice;
    }

    /**
     * @return bool
     */
    public function isVariableDeliveryPriceEnabled(): bool
    {
        return $this->variableDeliveryPriceEnabled;
    }

    /**
     * @param bool $variableDeliveryPriceEnabled
     */
    public function setVariableDeliveryPriceEnabled(bool $variableDeliveryPriceEnabled): void
    {
        $this->variableDeliveryPriceEnabled = $variableDeliveryPriceEnabled;
    }

    /**
     * @return PricingRuleSet|null
     */
    public function getVariableDeliveryPrice()
    {
        return $this->variableDeliveryPrice;
    }

    /**
     * @param PricingRuleSet|null $variableDeliveryPrice
     */
    public function setVariableDeliveryPrice(?PricingRuleSet $variableDeliveryPrice)
    {
        $this->variableDeliveryPrice = $variableDeliveryPrice;
    }

    /**
     * @return float
     */
    public function getFeeRate()
    {
        return $this->feeRate;
    }

    /**
     * @param float $feeRate
     * @return Contract
     */
    public function setFeeRate(float $feeRate)
    {
        $this->feeRate = $feeRate;

        return $this;
    }

    public function getCustomerAmount()
    {
        return $this->customerAmount;
    }

    public function setCustomerAmount(int $customerAmount)
    {
        $this->customerAmount = $customerAmount;

        return $this;
    }

    /**
     * @return bool
     */
    public function isRestaurantPaysStripeFee(): bool
    {
        return $this->restaurantPaysStripeFee;
    }

    /**
     * @param bool $restaurantPaysStripeFee
     */
    public function setRestaurantPaysStripeFee(bool $restaurantPaysStripeFee): void
    {
        $this->restaurantPaysStripeFee = $restaurantPaysStripeFee;
    }

    /**
     * @return PricingRuleSet|null
     */
    public function getVariableCustomerAmount()
    {
        return $this->variableCustomerAmount;
    }

    /**
     * @param PricingRuleSet|null $variableCustomerAmount
     *
     * @return self
     */
    public function setVariableCustomerAmount($variableCustomerAmount)
    {
        $this->variableCustomerAmount = $variableCustomerAmount;

        return $this;
    }

    /**
     * @return bool
     */
    public function isVariableCustomerAmountEnabled()
    {
        return $this->variableCustomerAmountEnabled;
    }

    /**
     * @param bool $variableCustomerAmountEnabled
     *
     * @return self
     */
    public function setVariableCustomerAmountEnabled($variableCustomerAmountEnabled)
    {
        $this->variableCustomerAmountEnabled = $variableCustomerAmountEnabled;

        return $this;
    }

    /**
     * @return float
     */
    public function getTakeAwayFeeRate()
    {
        return $this->takeAwayFeeRate;
    }

    /**
     * @param float $takeAwayFeeRate
     * @return Contract
     */
    public function setTakeAwayFeeRate(float $takeAwayFeeRate)
    {
        $this->takeAwayFeeRate = $takeAwayFeeRate;

        return $this;
    }
}
