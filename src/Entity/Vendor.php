<?php

namespace AppBundle\Entity;

use AppBundle\Entity\LocalBusiness\ShippingOptionsInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

interface Vendor extends ShippingOptionsInterface
{
    public function getId();

    public function getAddress();

    public function getOpeningHours($method = 'delivery');

    public function hasClosingRuleFor(\DateTime $date = null, \DateTime $now = null): bool;

    public function isFulfillmentMethodEnabled($method);

    public function getFulfillmentMethod(string $method);

    public function getFulfillmentMethods();

    public function getShippingOptionsDays();

    public function getClosingRules();

    /**
     * @return Contract
     */
    public function getContract();

    public function getName();

    public function canDeliverAddress(Address $address, $distance, ExpressionLanguage $language = null);

    public function getDeliveryPerimeterExpression();

    public function getOwners(): Collection;
}
