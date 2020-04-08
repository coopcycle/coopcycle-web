<?php

namespace AppBundle\Doctrine\DBAL\Types;

use AppBundle\DataType\TsRange;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

class TsRangeType extends Type
{
    const DATE_PATTERN = '(?<%year_subpattern%>[0-9]{4})-(?<%month_subpattern%>[0-9]{2})-(?<%day_subpattern%>[0-9]{2})';
    const TIME_PATTERN = '(?<%hour_subpattern%>[0-9]{1,2}):(?<%minute_subpattern%>[0-9]{1,2})(:[0-9]{1,2})?';

    public function canRequireSQLConversion()
    {
        return false;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'TSRANGE';
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return $value;
        }

        return sprintf('[%s,%s]',
            $value->getLower()->format('Y-m-d H:i'),
            $value->getUpper()->format('Y-m-d H:i')
        );
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if (null !== $value) {

            $lowerDatePattern = str_replace(
                ['%year_subpattern%', '%month_subpattern%', '%day_subpattern%'],
                ['lower_year', 'lower_month', 'lower_day'],
                self::DATE_PATTERN
            );
            $lowerTimePattern = str_replace(
                ['%hour_subpattern%', '%minute_subpattern%'],
                ['lower_hour', 'lower_minute'],
                self::TIME_PATTERN
            );

            $upperDatePattern = str_replace(
                ['%year_subpattern%', '%month_subpattern%', '%day_subpattern%'],
                ['upper_year', 'upper_month', 'upper_day'],
                self::DATE_PATTERN
            );
            $upperTimePattern = str_replace(
                ['%hour_subpattern%', '%minute_subpattern%'],
                ['upper_hour', 'upper_minute'],
                self::TIME_PATTERN
            );

            $pattern = sprintf('/^(\[|\()"?%s %s"?, *"?%s %s"?(\]|\))$/',
                $lowerDatePattern, $lowerTimePattern,
                $upperDatePattern, $upperTimePattern
            );

            preg_match($pattern, $value, $matches);

            $lower = new \DateTime();
            $lower->setDate($matches['lower_year'], $matches['lower_month'], $matches['lower_day']);
            $lower->setTime($matches['lower_hour'], $matches['lower_minute']);

            $upper = new \DateTime();
            $upper->setDate($matches['upper_year'], $matches['upper_month'], $matches['upper_day']);
            $upper->setTime($matches['upper_hour'], $matches['upper_minute']);

            $value = new TsRange();
            $value->setLower($lower);
            $value->setUpper($upper);
        }

        return $value;
    }

    public function getName()
    {
        return 'tsrange';
    }
}
