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
use Symfony\Component\Security\Core\Security;

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
            $address = new Address();

            $address->setStreetAddress($data['streetAddress']);

            $longitude = $data['longitude'];
            $latitude = $data['latitude'];

            if ($latitude && $longitude) {
                $address->setGeo(new GeoCoordinates($latitude, $longitude));
            }

            if (isset($data['provider']) && in_array($data['provider'], Address::PROVIDERS)) {
                $address->setProvider($data['provider']);
            }

            return yield $address;
        }

        yield null;
    }
}
