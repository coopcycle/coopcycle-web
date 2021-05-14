<?php

namespace AppBundle\Form;

use AppBundle\Entity\Zone;
use Doctrine\ORM\EntityRepository;
use GeoJson\Exception\UnserializationException;
use GeoJson\Feature\Feature;
use GeoJson\Feature\FeatureCollection;
use GeoJson\GeoJson;
use GeoJson\Geometry\Polygon;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GeoJSONUploadType extends AbstractType
{
    const ERROR_INVALID_JSON =
        'The file does not contain valid JSON';
    const ERROR_INVALID_GEOJSON =
        'The GeoJSON file must contain a Feature, a FeatureCollection, or a Polygon';
    const ERROR_POLYGON_ONLY =
        'The GeoJSON file must contain only Polygon geometries';

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('file', FormType\FileType::class, array(
                'mapped' => false,
                'required' => true,
                'label' => 'form.geojson_upload.file'
            ));

        $builder->get('file')->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $file = $event->getData();

            $contents = file_get_contents($file->getPathname());

            // Remove BOM
            // @see https://github.com/emrahgunduz/bom-cleaner/blob/master/bom.php
            if (substr($contents, 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf)) {
                $contents = substr($contents, 3);
            }

            $data = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $event->getForm()->addError(new FormError(self::ERROR_INVALID_JSON));
                return;
            }

            try {
                $geojson = GeoJson::jsonUnserialize($data);
                $event->getForm()->getParent()->setData($geojson);
            } catch (UnserializationException $e) {
                $event->getForm()->addError(new FormError($e->getMessage()));
                return;
            }

            if (!$geojson instanceof Polygon
            &&  !$geojson instanceof Feature
            &&  !$geojson instanceof FeatureCollection) {
                $event->getForm()->addError(new FormError(self::ERROR_INVALID_GEOJSON));
                return;
            }

            // Make sure we are always using a FeatureCollection
            if (!$geojson instanceof FeatureCollection) {
                if ($geojson instanceof Feature) {
                    $geojson = new FeatureCollection([$geojson]);
                }
                if ($geojson instanceof Polygon) {
                    $geojson = new FeatureCollection([new Feature($geojson)]);
                }
            }

            // Verify we are dealing with polygons
            $containsOnlyPolygons = true;
            foreach ($geojson as $feature) {
                if (!$feature->getGeometry() instanceof Polygon) {
                    $containsOnlyPolygons = false;
                    break;
                }
            }

            if (!$containsOnlyPolygons) {
                $event->getForm()->addError(new FormError(self::ERROR_POLYGON_ONLY));
                return;
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => FeatureCollection::class,
        ));
    }
}
