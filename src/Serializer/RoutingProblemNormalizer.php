<?php

namespace AppBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use AppBundle\Entity\RoutingProblem;

/**
* normalizes a given RoutingProblem into the Vroom api request format
*/

class RoutingProblemNormalizer implements NormalizerInterface
{

    public function normalize($object, $format = null, array $context = array())
    {
        $data = [
            "jobs"=> [],
            "vehicles"=>[],
        ];

        foreach ($object->getTasks() as $task) {
            $data["jobs"][] = [
                "id"=>$task->getId(),
                "location"=>[
                    $task->getAddress()->getGeo()->getLongitude(),
                    $task->getAddress()->getGeo()->getLatitude()
                ],
                "time_windows" => [
                    [
                        (int) $task->getAfter()->format('U'),
                        (int) $task->getBefore()->format('U')
                    ]
                ]
            ];
        }

        foreach ($object->getVehicles() as $vehicle) {

            // @see https://github.com/VROOM-Project/vroom/blob/master/docs/API.md#vehicle-locations
            //
            // - key start and end are optional for a vehicle, as long as at least one of them is present
            // - if end is omitted, the resulting route will stop at the last visited task, whose choice is determined by the optimization process
            // - if start is omitted, the resulting route will start at the first visited task, whose choice is determined by the optimization process
            // - to request a round trip, just specify both start and end with the same coordinates
            // - depending on if a custom matrix is provided, required fields follow the same logic than for job keys location and location_index

            $vehiclePayload = [
                "id" => $vehicle->getId(),
                "profile" => "bike",
            ];

            if (null !== $vehicle->getStart()) {
                $vehiclePayload['start'] = [
                    $vehicle->getStart()->getGeo()->getLongitude(),
                    $vehicle->getStart()->getGeo()->getLatitude(),
                ];
            }

            if (null !== $vehicle->getEnd()) {
                $vehiclePayload['end'] = [
                    $vehicle->getEnd()->getGeo()->getLongitude(),
                    $vehicle->getEnd()->getGeo()->getLatitude(),
                ];
            }

            $data["vehicles"][] = $vehiclePayload;
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof RoutingProblem;
    }

}
