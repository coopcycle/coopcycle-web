<?php

namespace AppBundle\Controller\ArgumentResolver;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\ItemNotFoundException;
use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Yields an Address object if defined in the query string as a base64 encoded IRI.
 *
 * {@link \AppBundle\Controller\RestaurantController::indexAction()}.
 */
class AddressValueResolver implements ArgumentValueResolverInterface
{
    private $security;
    private $iriConverter;

    public function __construct(Security $security, IriConverterInterface $iriConverter)
    {
        $this->security = $security;
        $this->iriConverter = $iriConverter;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $request->attributes->get('_route') === 'restaurant' && Address::class === $argument->getType();
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Traversable|array
    {
        if (!$request->query->has('address')) {
            return yield null;
        }

        $value = $request->query->get('address');

        if (empty($value)) {
            return yield null;
        }

        $user = $this->security->getUser();

        $data = urldecode(base64_decode($value));
        $data = json_decode($data, true);

        if (!is_array($data)) {
            return yield null;
        }

        $address = null;

        if (is_object($user) && count($user->getAddresses()) > 0 && isset($data['@id'])) {
            try {
                $address = $this->iriConverter->getResourceFromIri($data['@id']);

                if ($user->getAddresses()->contains($address)) {
                    return yield $address;
                }
            } catch (InvalidArgumentException | ItemNotFoundException $e) {}
        }

        if (isset($data['streetAddress'])) {
            [ $latitude, $longitude ] = $this->getCoordinates($data);

            // The address will be persisted with the cart,
            // and it can't be persisted without coordinates
            if (is_null($latitude) || is_null($longitude)) {
                return yield null;
            }

            $address = new Address();

            $address->setStreetAddress($data['streetAddress']);
            $address->setGeo(new GeoCoordinates($latitude, $longitude));

            if (isset($data['provider']) && in_array($data['provider'], Address::PROVIDERS)) {
                $address->setProvider($data['provider']);
            }

            return yield $address;
        }

        yield null;
    }

    /**
     * Supports both the flat shape ({"latitude":..,"longitude":..}),
     * and the JSON-LD shape of a saved address ({"geo":{"latitude":..,"longitude":..}}).
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function getCoordinates(array $data): array
    {
        $latitude = $data['latitude'] ?? $data['geo']['latitude'] ?? null;
        $longitude = $data['longitude'] ?? $data['geo']['longitude'] ?? null;

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return [ null, null ];
        }

        return [ (float) $latitude, (float) $longitude ];
    }
}
