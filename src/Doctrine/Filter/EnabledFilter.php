<?php

namespace AppBundle\Doctrine\Filter;

use AppBundle\Annotation\Enabled;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\Common\Annotations\Reader;
use Doctrine\DBAL\Types\Type;

/**
 * A Doctrine filter that adds "enabled = X" to SQL queries.
 *
 * @see https://api-platform.com/docs/core/filters#using-doctrine-filters
 * @see http://blog.michaelperrin.fr/2014/12/05/doctrine-filters/
 */
final class EnabledFilter extends SQLFilter
{
    private $reader;

    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias)
    {
        if (empty($this->reader)) {
            return '';
        }

        $enabledAware = $this->reader->getClassAnnotation($targetEntity->getReflectionClass(), Enabled::class);
        if (!$enabledAware) {
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

    public function setAnnotationReader(Reader $reader)
    {
        $this->reader = $reader;
    }
}
