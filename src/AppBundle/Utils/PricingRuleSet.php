<?php

namespace AppBundle\Utils;

use Doctrine\Common\Collections\ArrayCollection;

class PricingRuleSet extends ArrayCollection
{
    public function setRules(array $rules)
    {
        $this->clear();
        foreach ($rules as $rule) {
            $this->add($rule);
        }

        return $this;
    }

    public function getRules()
    {
        return $this->toArray();
    }
}
