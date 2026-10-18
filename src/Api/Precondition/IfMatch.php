<?php

namespace AppBundle\Api\Precondition;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reads the version a client claims to have written against.
 *
 * Collections that are replaced wholesale carry a `version`, and clients echo
 * it back as `If-Match` so a write built from a stale read can be refused
 * instead of quietly winning. Parsing lives here because each endpoint decides
 * for itself whether the header is optional — `PUT /api/tours/{id}` accepts
 * writes without one, so it can ship ahead of the clients, while the endpoints
 * added since require it — but none of them should disagree about what a
 * well-formed one looks like.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
final class IfMatch
{
    /**
     * The version the client claims, or null when it sent no precondition.
     *
     * @throws BadRequestHttpException when a header is present but unusable.
     *         That is the client's bug, not a conflict: answering 409 would
     *         have the dashboard discard the dispatcher's edit and refetch
     *         over a header it got wrong.
     */
    public static function expectedVersion(Request $request): ?int
    {
        $ifMatch = $request->headers->get('If-Match');

        if (null === $ifMatch || '' === $ifMatch || '*' === $ifMatch) {
            return null;
        }

        // Accept both a bare version and a properly quoted entity tag.
        $expected = trim(ltrim(trim($ifMatch), 'W/'), '"');

        if (!ctype_digit($expected)) {
            throw new BadRequestHttpException(sprintf('Malformed If-Match: %s', $ifMatch));
        }

        return (int) $expected;
    }
}
