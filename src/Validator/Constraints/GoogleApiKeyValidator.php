<?php

namespace AppBundle\Validator\Constraints;

use AppBundle\Utils\Settings;
use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
use Geocoder\Query\ReverseQuery;
use Geocoder\StatefulGeocoder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\HttpClient\HttplugClient;

class GoogleApiKeyValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        $object = $this->context->getObject();

        // Paris, France
        $latitude = 48.856613;
        $longitude = 2.352222;

        if ($object instanceof Settings) {
            [ $latitude, $longitude ] = explode(',', $object->latlng);
        }

        $httpClient = new HttplugClient();

        $geocoder = new StatefulGeocoder(
            new GoogleMaps(client: $httpClient, apiKey: $value)
        );

        try {
            $results = $geocoder->reverseQuery(
                ReverseQuery::fromCoordinates($latitude, $longitude)
            );
        } catch (InvalidCredentials | InvalidServerResponse $e) {
            $this->context->buildViolation($constraint->invalidApiKeyMessage)
                ->setCode(GoogleApiKey::INVALID_API_KEY_ERROR)
                ->addViolation();
        }
    }
}
