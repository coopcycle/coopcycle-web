<?php

namespace AppBundle\Geocoder\Provider;

use Geocoder\Collection;
use Geocoder\Exception\InvalidArgument;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Http\Provider\AbstractHttpProvider;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Psr\Http\Client\ClientInterface;

/**
 * Drop-in replacement for the upstream addok provider
 * (geo6/geocoder-php-addok-provider) that forwards the `proximity` data
 * key — set by AppBundle\Service\Geocoder to the instance's lat/lng — and
 * the query locale to the addok REST API as `lat`/`lon` and `lang` query
 * parameters.
 *
 * Upstream v1.6.0 declares the Addok class as `final` and keeps URL
 * building private, so this provider re-implements the URL build and JSON
 * parse loop verbatim. Keep this file in sync with the upstream provider
 * when upgrading.
 */
final class AddokWithProximityProvider extends AbstractHttpProvider
{
    public const TYPE_HOUSENUMBER = 'housenumber';
    public const TYPE_STREET = 'street';
    public const TYPE_LOCALITY = 'locality';
    public const TYPE_MUNICIPALITY = 'municipality';

    private string $rootUrl;

    public function __construct(ClientInterface $client, string $rootUrl)
    {
        parent::__construct($client);
        $this->rootUrl = rtrim($rootUrl, '/');
    }

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        $address = $query->getText();
        // This API does not support IP
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The Addok provider does not support IP addresses, only street addresses.');
        }

        // Save a request if no valid address entered
        if (empty($address)) {
            throw new InvalidArgument('Address cannot be empty.');
        }

        $url = sprintf('%s/search/?q=%s&limit=%d&autocomplete=0',
            $this->rootUrl,
            urlencode($address),
            $query->getLimit()
        );

        if ($type = $query->getData('type', null)) {
            $url .= sprintf('&type=%s', $type);
        }

        $proximity = $query->getData('proximity', null);
        if (is_string($proximity) && '' !== $proximity) {
            $parts = preg_split('/\s*,\s*/', trim($proximity));
            if (is_array($parts) && 2 === count($parts)) {
                $lat = filter_var($parts[0], FILTER_VALIDATE_FLOAT);
                $lon = filter_var($parts[1], FILTER_VALIDATE_FLOAT);
                if (false !== $lat && false !== $lon
                    && $lat >= -90.0 && $lat <= 90.0
                    && $lon >= -180.0 && $lon <= 180.0) {
                    // See: https://addok.readthedocs.io/en/latest/api/#parameters
                    $url .= sprintf('&lat=%F&lon=%F', $lat, $lon);
                }
            }
        }

        $locale = $query->getLocale();
        if (is_string($locale) && '' !== $locale) {
            // See: https://addok.readthedocs.io/en/latest/api/#parameters
            $url .= sprintf('&lang=%s', urlencode($locale));
        }

        $json = $this->executeQuery($url);

        // no result
        if (empty($json->features)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($json->features as $feature) {
            $coordinates = $feature->geometry->coordinates;

            switch ($feature->properties->type) {
                case self::TYPE_HOUSENUMBER:
                    $streetName = !empty($feature->properties->street) ? $feature->properties->street : null;
                    $number = !empty($feature->properties->housenumber) ? $feature->properties->housenumber : null;
                    break;
                case self::TYPE_STREET:
                    $streetName = !empty($feature->properties->name) ? $feature->properties->name : null;
                    $number = null;
                    break;
                default:
                    $streetName = null;
                    $number = null;
            }
            $locality = !empty($feature->properties->city) ? $feature->properties->city : null;
            $postalCode = !empty($feature->properties->postcode) ? $feature->properties->postcode : null;

            $results[] = Address::createFromArray([
                'providedBy'   => $this->getName(),
                'latitude'     => $coordinates[1],
                'longitude'    => $coordinates[0],
                'streetNumber' => $number,
                'streetName'   => $streetName,
                'locality'     => $locality,
                'postalCode'   => $postalCode,
                'adminLevels'  => $this->getAdminLevels($feature->properties),
            ]);
        }

        return new AddressCollection($results);
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        $coordinates = $query->getCoordinates();

        $url = sprintf('%s/reverse/?lat=%F&lon=%F',
            $this->rootUrl,
            $coordinates->getLatitude(),
            $coordinates->getLongitude()
        );
        $json = $this->executeQuery($url);

        // no result
        if (empty($json->features)) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($json->features as $feature) {
            $coordinates = $feature->geometry->coordinates;
            $streetName = !empty($feature->properties->street) ? $feature->properties->street : null;
            $number = !empty($feature->properties->housenumber) ? $feature->properties->housenumber : null;
            $municipality = !empty($feature->properties->city) ? $feature->properties->city : null;
            $postalCode = !empty($feature->properties->postcode) ? $feature->properties->postcode : null;

            $results[] = Address::createFromArray([
                'providedBy'   => $this->getName(),
                'latitude'     => $coordinates[1],
                'longitude'    => $coordinates[0],
                'streetNumber' => $number,
                'streetName'   => $streetName,
                'locality'     => $municipality,
                'postalCode'   => $postalCode,
                'adminLevels'  => $this->getAdminLevels($feature->properties),
            ]);
        }

        return new AddressCollection($results);
    }

    public function getName(): string
    {
        return 'addok';
    }

    private function executeQuery(string $url): \stdClass
    {
        $content = $this->getUrlContents($url);
        $json = json_decode($content);
        // API error
        if (!isset($json)) {
            throw InvalidServerResponse::create($url);
        }

        return $json;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAdminLevels(\stdClass $properties): array
    {
        $adminLevels = [];

        $context = !empty($properties->context) ? $properties->context : null;
        if ($context) {
            $contextParts = explode(',', $context);
            $departmentCode = trim($contextParts[0] ?? '');
            $departementLabel = trim($contextParts[1] ?? '');
            $regionLabel = trim($contextParts[2] ?? '');

            $adminLevels[] = ['level' => 2, 'name' => $regionLabel];
            $adminLevels[] = ['level' => 3, 'name' => $departementLabel, 'code' => $departmentCode];
        }

        $cityCode = !empty($properties->citycode) ? $properties->citycode : null;
        $municipality = !empty($properties->city) ? $properties->city : null;
        if ($cityCode && $municipality) {
            $adminLevels[] = ['level' => 4, 'name' => $municipality, 'code' => $cityCode];
        }

        $district = !empty($properties->district) ? $properties->district : null;
        $adminLevels[] = ['level' => 5, 'name' => $district];

        return $adminLevels;
    }
}
