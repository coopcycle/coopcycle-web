<?php

namespace AppBundle\Doctrine\Filter;

use AppBundle\Entity\LocalBusiness;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\DBAL\Types\Type;

/**
 * A Doctrine filter that adds "enabled = X" to SQL queries.
 *
 * @see https://api-platform.com/docs/core/filters/#using-doctrine-orm-filters
 * @see http://blog.michaelperrin.fr/2014/12/05/doctrine-filters/
 */
final class RestaurantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $metadata, $targetTableAlias)
    {
        if ($metadata->getReflectionClass()->getName() !== LocalBusiness::class) {
            return '';
        }

        try {
            $enabled = $this->getParameter('enabled');
        } catch (\InvalidArgumentException $e) {
            return '';
        }

        if ($this->hasParameter('restaurants')) {
            return sprintf('(%s.enabled = %s OR (%s.enabled = %s AND %s.id IN(%s)))',
                $targetTableAlias, $enabled,
                $targetTableAlias, $this->getConnection()->quote(false, Type::BOOLEAN),
                // @see https://github.com/doctrine/doctrine2/issues/5811
                $targetTableAlias, str_replace("'", '', $this->getParameter('restaurants'))
            );
        }

        return sprintf('%s.enabled = %s', $targetTableAlias, $enabled);
    }
}
