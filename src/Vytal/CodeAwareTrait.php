<?php

namespace AppBundle\Vytal;

use Symfony\Component\Serializer\Annotation\Groups;

trait CodeAwareTrait
{
    /**
     * @var string|null
     */
    protected $vytalCode;

    /**
     * @return string|null
     */
    public function getVytalCode()
    {
        return $this->vytalCode;
    }

    /**
     * @param string|null $vytalCode
     *
     * @return self
     */
    public function setVytalCode($vytalCode)
    {
        $this->vytalCode = $vytalCode;

        return $this;
    }
}
