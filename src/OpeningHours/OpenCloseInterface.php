<?php

namespace AppBundle\OpeningHours;

use AppBundle\Entity\ClosingRule;
use Doctrine\Common\Collections\Collection;

interface OpenCloseInterface
{
    /**
     * @return array
     */
    public function getOpeningHours($method = 'delivery');

    public function isOpen(?\DateTime $now = null): bool;

    /**
     * @return \DateTime|null
     */
    public function getNextOpeningDate(?\DateTime $now = null);

    /**
     * @return \DateTime|null
     */
    public function getNextClosingDate(?\DateTime $now = null);

    /**
     * @return Collection
     */
    public function getClosingRules();

    /**
     * @param \DateTime|null $date
     * @param \DateTime|null $now
     */
    public function hasClosingRuleFor(?\DateTime $date = null, ?\DateTime $now = null): bool;

    /**
     * @param \DateTime|null $date
     * @param \DateTime|null $now
     */
    public function matchClosingRuleFor(?\DateTime $date = null, ?\DateTime $now = null): ?ClosingRule;

    public function setShippingOptionsDays(int $shippingOptionsDays);
}
