<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\ClosingRule;
use Carbon\Carbon;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Serializer\Annotation\Groups;

trait ClosingRulesTrait
{
    protected $closingRules;

    /**
     * @return mixed
     */
    public function getClosingRules()
    {
        return $this->closingRules;
    }

    public function addClosingRule(ClosingRule $closingRule)
    {
        $this->closingRules->add($closingRule);
    }

    public function removeClosingRule(ClosingRule $closingRule)
    {
        $this->closingRules->removeElement($closingRule);
    }

    #[Groups(['restaurant'])]
    public function getSpecialOpeningHoursSpecification()
    {
        // We return only the closing rules that have not expired
        $expr = Criteria::expr();
        $criteria = Criteria::create()->where($expr->gte('endDate', Carbon::now()));

        return $this->closingRules->matching($criteria);
    }
}
