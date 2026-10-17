<?php

namespace AppBundle\EventListener;

use ApiPlatform\Metadata\HttpOperation;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Tells clients, in the response itself, that the operation they just called
 * is on its way out.
 *
 * API Platform already turns `deprecationReason` into `deprecated: true` in
 * the OpenAPI document and `sunset` into a `Sunset` header. Neither reaches a
 * client that does not read the docs and is not yet looking for the date it
 * stops working, so this adds the two fields that do:
 *
 *  - `Deprecation`, an RFC 9745 structured-field date saying since when
 *  - `Link ...; rel="successor-version"`, saying what to call instead
 *
 * Driven by the operation's own metadata, so any operation marked deprecated
 * gets the same treatment without touching this class.
 */
final class DeprecatedOperationListener
{
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $operation = $event->getRequest()->attributes->get('_api_operation');

        if (!$operation instanceof HttpOperation || null === $operation->getDeprecationReason()) {
            return;
        }

        $extraProperties = $operation->getExtraProperties();
        $response = $event->getResponse();

        if (isset($extraProperties['deprecated_since'])) {
            $response->headers->set('Deprecation', sprintf(
                '@%d',
                (new \DateTimeImmutable($extraProperties['deprecated_since']))->getTimestamp()
            ));
        }

        foreach ((array) ($extraProperties['successor_version'] ?? []) as $successor) {
            // Appended rather than set: an operation may have more than one
            // replacement, and API Platform puts its own Link headers here.
            $response->headers->set(
                'Link',
                sprintf('<%s>; rel="successor-version"', $successor),
                false
            );
        }
    }
}
