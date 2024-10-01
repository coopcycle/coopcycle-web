<?php

namespace AppBundle\DataType;

use DateTime;

class TsRange
{
    public $lower;
    public $upper;

    const TIME_RANGE_PATTERN = '/^(?<lower>[0-9-T:\+]+) - (?<upper>[0-9-T:\+]+)$/';

    /**
    * @var \DateTime
    */
    private $lower;

    /**
    * @var \DateTime
    */
    private $upper;

    /**
     * @return \DateTime
     */
    public function getLower(): \DateTimeInterface
    {
        return $this->lower;
    }

    /**
     * @param \DateTime $lower
     *
     * @return self
     */
    public function setLower(\DateTimeInterface $lower)
    {
        $this->lower = $lower;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpper(): \DateTimeInterface
    {
        return $this->upper;
    }

    /**
     * @param \DateTime $upper
     *
     * @return self
     */
    public function setUpper(\DateTimeInterface $upper)
    {
        $this->upper = $upper;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getMidPoint(): \DateTimeInterface
    {
        return new DateTime($this->lower->getTimestamp() + round($this->lower->getTimestamp() + $this->upper->getTimestamp()) / 2);
    }

    public static function parse(string $text): ?TsRange
    {
        if (1 === preg_match(self::TIME_RANGE_PATTERN, $text, $matches)) {
            $range = new self();
            $range->setLower(new \DateTime($matches['lower']));
            $range->setUpper(new \DateTime($matches['upper']));

            return $range;
        }

        return null;
    }

    public static function create($lower, $upper): TsRange
    {
        $range = new self();

        $range->setLower($lower);
        $range->setUpper($upper);

        return $range;
    }
}
