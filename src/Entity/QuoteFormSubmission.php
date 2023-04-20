<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;

class QuoteFormSubmission
{
    use Timestampable;

    private $id;
    private $quoteForm;
    private $data;
    private $price;
    private $pricingRuleSet;

    /**
     * @return mixed
     */
    public function getPricingRuleSet()
    {
        return $this->pricingRuleSet;
    }

    /**
     * @param mixed $pricingRuleSet
     *
     * @return self
     */
    public function setPricingRuleSet($pricingRuleSet)
    {
        $this->pricingRuleSet = $pricingRuleSet;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getQuoteForm()
    {
        return $this->quoteForm;
    }

    /**
     * @param mixed $quoteForm
     *
     * @return self
     */
    public function setQuoteForm($quoteForm)
    {
        $this->quoteForm = $quoteForm;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     *
     * @return self
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param mixed $price
     *
     * @return self
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }
}
