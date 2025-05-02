<?php

namespace AppBundle\Serializer;

use AppBundle\DataType\TsRange;
use Carbon\CarbonPeriod;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class TsRangeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        return [
            $object->getLower()->format(\DateTime::ATOM),
            $object->getUpper()->format(\DateTime::ATOM)
        ];
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof TsRange;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if (is_array($data) && count($data) === 2) {
            $tsRange = new TsRange();
            $tsRange->setLower(new \DateTime($data[0]));
            $tsRange->setUpper(new \DateTime($data[1]));

            return $tsRange;
        } else if (is_string($data)) {
            //example: 2024-01-01 14:30-18:45
            if (1 === preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}) ([0-9:]+-[0-9:]+)$/', $data, $matches)) {

                $date = $matches[1];
                $timeRange = $matches[2];

                [ $start, $end ] = explode('-', $timeRange);

                [ $startHour, $startMinute ] = explode(':', $start);
                [ $endHour, $endMinute ] = explode(':', $end);

                $lower = new \DateTime($date);
                $lower->setTime($startHour, $startMinute);

                $upper = new \DateTime($date);
                $upper->setTime($endHour, $endMinute);

                return TsRange::create($lower, $upper);
            } else {

                //example: 2022-08-12T10:00:00Z/2022-08-12T12:00:00Z

                $tz = date_default_timezone_get();

                // FIXME Catch Exception
                $period = CarbonPeriod::createFromIso($data);

                $lower = $period->getStartDate()->tz($tz)->toDateTime();
                $upper = $period->getEndDate()->tz($tz)->toDateTime();

                return TsRange::create($lower, $upper);
            }
        }

        return [];
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === TsRange::class;
    }
}
