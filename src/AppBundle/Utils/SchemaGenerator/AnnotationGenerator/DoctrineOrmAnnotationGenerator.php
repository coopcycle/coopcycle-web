<?php

/*
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Utils\SchemaGenerator\AnnotationGenerator;

use ApiPlatform\SchemaGenerator\AnnotationGenerator\DoctrineOrmAnnotationGenerator as BaseDoctrineOrmAnnotationGenerator;

// use ApiPlatform\SchemaGenerator\CardinalitiesExtractor;
// use ApiPlatform\SchemaGenerator\TypesGenerator;

class DoctrineOrmAnnotationGenerator extends BaseDoctrineOrmAnnotationGenerator
{
    /**
     * {@inheritdoc}
     */
    public function generateFieldAnnotations($className, $fieldName)
    {
        $annotations = parent::generateFieldAnnotations($className, $fieldName);

        $field = $this->classes[$className]['fields'][$fieldName];

        if ($field['range'] === 'GeoCoordinates') {
            $annotations = array('@ORM\Column(type="geography")');
        }

        return $annotations;
    }
}
