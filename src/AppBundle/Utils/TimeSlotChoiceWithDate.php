<?php

namespace AppBundle\Utils;

use AppBundle\Entity\TimeSlot\Choice;
use Sylius\Component\Currency\Context\CurrencyContextInterface;

class TimeSlotChoiceWithDate
{
    private $choice;
    private $date;

    public function __construct(Choice $choice, \DateTime $date)
    {
        $this->choice = $choice;
        $this->date = $date;
    }

    /**
     * @return mixed
     */
    public function getChoice()
    {
        return $this->choice;
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    public function toDateTime()
    {
        return $this->choice->toDateTime($this->date);
    }
}
