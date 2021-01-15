<?php

namespace AppBundle\Controller\ArgumentResolver;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Entity\Address;
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

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        return $request->attributes->get('_route') === 'restaurant' && Address::class === $argument->getType();
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        if (!$request->query->has('address')) {
            return yield null;
        }

        $value = $request->query->get('address');

        if (empty($value)) {
            return yield null;
        }

        $user = $this->security->getUser();

        if (!is_object($user)) {
            // e.g. anonymous authentication
            return yield null;
        }

        if (count($user->getAddresses()) === 0) {
            return yield null;
        }

        try {

            $address = $this->iriConverter->getItemFromIri(
                base64_decode($value)
            );

            if ($user->getAddresses()->contains($address)) {

                return yield $address;
            }

        } catch (InvalidArgumentException | ItemNotFoundException $e) {}

        yield null;
    }
}
