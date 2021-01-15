<?php

namespace AppBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use AppBundle\DataType\RoutingProblem;

/**
* normalizes a given RoutingProblem into the Vroom api request format
*/

class RoutingProblemNormalizer implements NormalizerInterface
{
    public function normalize($object, $format = null, array $context = array())
    {
        $data = [
            "jobs"=> [],
            "shipments"=> [],
            "vehicles"=>[],
        ];

        foreach ($object->getJobs() as $job) {
            $data["jobs"][] = [
                "id"=>$job->id,
                "location"=>$job->location,
                "time_windows" => $job->time_windows
            ];
        }

        foreach ($object->getShipments() as $shipment) {
            $data["shipments"][] = [
                "pickup"=>[
                    "id"=>$shipment->pickup->id,
                    "location"=>$shipment->pickup->location,
                    "time_windows" => $shipment->pickup->time_windows
                ],
                "delivery"=>[
                    "id"=>$shipment->delivery->id,
                    "location"=>$shipment->delivery->location,
                    "time_windows" => $shipment->delivery->time_windows
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
