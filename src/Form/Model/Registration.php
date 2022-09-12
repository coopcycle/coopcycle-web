<?php

namespace AppBundle\Form\Model;

use Nucleos\ProfileBundle\Form\Model\Registration as BaseRegistration;

class Registration extends BaseRegistration
{
    /**
     * @var bool
     */
    protected $legal = false;

    /**
     * @var bool
     */
    protected $termsAndConditions = false;

    /**
     * @var bool
     */
    protected $privacyPolicy = false;

    /**
     * @return mixed
     */
    public function getLegal()
    {
        return $this->legal;
    }

    /**
     * @param mixed $legal
     *
     * @return self
     */
    public function setLegal($legal)
    {
        $this->legal = $legal;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTermsAndConditions()
    {
        return $this->termsAndConditions;
    }

    /**
     * @param mixed $termsAndConditions
     *
     * @return self
     */
    public function setTermsAndConditions($termsAndConditions)
    {
        $this->termsAndConditions = $termsAndConditions;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPrivacyPolicy()
    {
        return $this->privacyPolicy;
    }

    /**
     * @param mixed $privacyPolicy
     *
     * @return self
     */
    public function setPrivacyPolicy($privacyPolicy)
    {
        $this->privacyPolicy = $privacyPolicy;

        return $this;
    }
}
