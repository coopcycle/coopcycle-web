<?php

namespace AppBundle\Entity\Model;

use Doctrine\ORM\Mapping as ORM;

trait TaxableTrait
{
    /**
     * @ORM\Column(type="float")
     */
    private $totalExcludingTax;

    /**
     * @ORM\Column(type="float")
     */
    private $totalTax;

    /**
     * @ORM\Column(type="float")
     */
    private $totalIncludingTax;

    public function getTotalExcludingTax()
    {
        return $this->totalExcludingTax;
    }

    public function setTotalExcludingTax($totalExcludingTax)
    {
        $this->totalExcludingTax = $totalExcludingTax;

        return $this;
    }

    public function getTotalTax()
    {
        return $this->totalTax;
    }

    public function setTotalTax($totalTax)
    {
        $this->totalTax = $totalTax;

        return $this;
    }

    public function getTotalIncludingTax()
    {
        return $this->totalIncludingTax;
    }

    public function setTotalIncludingTax($totalIncludingTax)
    {
        $this->totalIncludingTax = $totalIncludingTax;

        return $this;
    }
}
