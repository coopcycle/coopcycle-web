<?php

namespace AppBundle\Doctrine\Filter;

use AppBundle\Entity\Sylius\ProductOptionValue;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\DBAL\Types\Type;

/**
 * A Doctrine filter that adds "enabled = true" to SQL queries.
 *
 * @see https://api-platform.com/docs/core/filters/#using-doctrine-orm-filters
 * @see http://blog.michaelperrin.fr/2014/12/05/doctrine-filters/
 */
final class DisabledFilter extends SQLFilter
{
    private $classes = [
        ProductOptionValue::class
    ];

    public function addFilterConstraint(ClassMetadata $metadata, $targetTableAlias)
    {
        if (!in_array($metadata->getReflectionClass()->getName(), $this->classes)) {
            return '';
        }

        return sprintf('%s.enabled = %s',
            $targetTableAlias, $this->getConnection()->quote(true, Type::getType('boolean'))
        );
    }

    public function add(string $class)
    {
        $this->classes[] = $class;
    }
}
