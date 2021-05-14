<?php

namespace AppBundle\Doctrine\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Recurr\Rule;

class RRuleType extends Type
{
    public function canRequireSQLConversion()
    {
        return false;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform)
    {
        return $platform->getVarcharTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value === null) {
            return $value;
        }

        return $value->getString();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if (null !== $value) {

            return new Rule($value);
        }

        return $value;
    }

    public function getName()
    {
        return 'rrule';
    }
}
