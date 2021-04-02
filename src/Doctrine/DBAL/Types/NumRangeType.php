<?php

namespace AppBundle\Doctrine\DBAL\Types;

use AppBundle\DataType\NumRange;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

class NumRangeType extends Type
{
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
        return 'NUMRANGE';
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return $value;
        }

        return sprintf('[%s,%s]', $value->getLower(), $value->getUpper() === 'INF' ? '' : $value->getUpper());
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if (null !== $value) {

            preg_match('/^(\[|\()([0-9]+),([0-9]*)(\]|\))$/', $value, $matches);

            $lower = $matches[2];
            $upper = $matches[3];

            $value = new NumRange();
            $value->setLower($lower);
            $value->setUpper(empty($upper) ? 'INF' : $upper);
        }

        return $value;
    }

    public function getName()
    {
        return 'numrange';
    }
}
