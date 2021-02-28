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
}
