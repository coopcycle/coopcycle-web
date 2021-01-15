<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\ClosingRule;
use Symfony\Component\Serializer\Annotation\Groups;

trait ClosingRulesTrait
{
    /**
     * @Groups({"restaurant"})
     */
    protected $closingRules;

    /**
     * @return mixed
     */
    public function getClosingRules()
    {
        return $this->closingRules;
    }

    /**
     * @param ClosingRule $closingRule
     */
    public function addClosingRule(ClosingRule $closingRule)
    {
        $this->closingRules->add($closingRule);
    }

    /**
     * @param ClosingRule $closingRule
     */
    public function removeClosingRule(ClosingRule $closingRule)
    {
        $this->closingRules->removeElement($closingRule);
    }
}
