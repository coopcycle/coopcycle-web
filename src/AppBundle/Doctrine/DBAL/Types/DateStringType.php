<?php

namespace AppBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class DateTimeToString extends \DateTime
{
    public function __toString()
    {
        return $this->format('Y-m-d');
    }
}

class DateStringType extends DateTimeType
{
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return $value;
        }

        if (!$value instanceof \DateTimeInterface) {
            $value = new \DateTime($value);
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $dateTime = parent::convertToPHPValue($value, $platform);

        if ( ! $dateTime) {
            return $dateTime;
        }

        return new DateTimeToString($dateTime->format('Y-m-d'));
    }

    public function getName()
    {
        return 'date_string';
    }
}
