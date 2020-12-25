<?php

namespace AppBundle\DataType;

class TsRange
{
    const TIME_RANGE_PATTERN = '/^(?<lower>[0-9-T:\+]+) - (?<upper>[0-9-T:\+]+)$/';

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
