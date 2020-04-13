<?php

namespace AppBundle\DataType;

class TsRange
{
    /**
     * @return \DateTime
     */
    public function getLower(): \DateTime
    {
        return $this->lower;
    }

    /**
     * @param \DateTime $lower
     *
     * @return self
     */
    public function setLower(\DateTime $lower)
    {
        $this->lower = $lower;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpper(): \DateTime
    {
        return $this->upper;
    }

    /**
     * @param \DateTime $upper
     *
     * @return self
     */
    public function setUpper(\DateTime $upper)
    {
        $this->upper = $upper;

        return $this;
    }
}
