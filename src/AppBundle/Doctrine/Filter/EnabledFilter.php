<?php

namespace AppBundle\Doctrine\Filter;

use AppBundle\Annotation\Enabled;
use Doctrine\ORM\Mapping\ClassMetaData;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Doctrine\Common\Annotations\Reader;

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

        $disabledAware = $this->reader->getClassAnnotation($targetEntity->getReflectionClass(), Enabled::class);
        if (!$disabledAware) {
            return '';
        }

        try {
            $enabled = $this->getParameter('enabled');
        } catch (\InvalidArgumentException $e) {
            return '';
        }

        return sprintf('%s.enabled = %s', $targetTableAlias, $enabled);
    }

    public function setAnnotationReader(Reader $reader)
    {
        $this->reader = $reader;
    }
}
