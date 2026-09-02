<?php

namespace AppBundle\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Symfony's ExceptionListener & SchebTwoFactorBundle's AuthenticationRequiredHandler
 * store the URI of any denied "safe" request in the session, to redirect the user
 * there once they are authenticated.
 *
 * Background requests (XHR/fetch) triggered while the user sits on the login or the
 * 2FA form are denied as well, so they may overwrite the target path, and the user
 * ends up on a JSON endpoint (typically /cart.json) after submitting the 2FA code.
 *
 * This listener drops the target path when it points to a request that was not a
 * document navigation.
 */
class TargetPathListener
{
    private const TARGET_PATH_PREFIX = '_security.';
    private const TARGET_PATH_SUFFIX = '.target_path';

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        $session = $request->getSession();

        if (!$session->isStarted() || $this->isNavigation($request)) {
            return;
        }

        $uri = $request->getUri();

        foreach ($session->all() as $key => $value) {
            if (!str_starts_with($key, self::TARGET_PATH_PREFIX) || !str_ends_with($key, self::TARGET_PATH_SUFFIX)) {
                continue;
            }

            if ($value === $uri) {
                $session->remove($key);
            }
        }
    }

    /**
     * Whether the request is a top-level document navigation, i.e. the only kind of
     * request the user can be redirected to after authentication.
     */
    private function isNavigation(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return false;
        }

        // Sent by all modern browsers, "document" means a top-level navigation,
        // while XHR/fetch requests are sent with "empty".
        $dest = $request->headers->get('Sec-Fetch-Dest');

        if (null !== $dest) {
            return 'document' === $dest;
        }

        // Fallback for clients not sending Sec-Fetch-* headers: a browser navigation
        // explicitly asks for HTML, while XHR/fetch libraries do not.
        $acceptable = $request->getAcceptableContentTypes();

        if (empty($acceptable)) {
            return true;
        }

        return in_array('text/html', $acceptable, true)
            || in_array('application/xhtml+xml', $acceptable, true);
    }
}
