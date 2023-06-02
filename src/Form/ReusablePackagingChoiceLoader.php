<?php

namespace AppBundle\Form;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class ReusablePackagingChoiceLoader implements ChoiceLoaderInterface
{
    public function __construct(
    	private LocalBusiness $restaurant)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoiceList($value = null)
    {
        $packagings = $this->restaurant->getReusablePackagings();

        return new ArrayChoiceList($packagings, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function loadChoicesForValues(array $values, $value = null)
    {
        // Optimize
        if (empty($values)) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    /**
     * {@inheritdoc}
     */
    public function loadValuesForChoices(array $choices, $value = null)
    {
        // Optimize
        if (empty($choices)) {
            return [];
        }

        return $this->loadChoiceList($value)->getValuesForChoices($choices);
    }
}
