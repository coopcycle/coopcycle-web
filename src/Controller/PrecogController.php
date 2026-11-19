<?php

namespace AppBundle\Controller;

use AppBundle\Service\Precog\PrecogTracker;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class PrecogController extends AbstractController
{
    public function __construct(private PrecogTracker $precogTracker)
    {
    }

    /**
     * Heartbeat from a checkout page.
     *
     * Open to anonymous visitors on purpose -- guest checkout is a supported way
     * to order, and a funnel view that silently omitted guests would be worse
     * than no funnel view. The cart comes from the session, never from the
     * request body, so a caller can only ever describe their own cart.
     */
    #[Route(path: '/precog/ping', name: 'precog_ping', methods: ['POST'])]
    public function pingAction(Request $request, CartContextInterface $cartContext): JsonResponse
    {
        if (!$this->precogTracker->isEnabled()) {
            return new JsonResponse(['stage' => null]);
        }

        $page = $request->request->get('page');
        $submitted = $request->request->getBoolean('submitted');

        try {
            $cart = $cartContext->getCart();
        } catch (\Exception $e) {
            // No session, no cart, nothing to forecast. Not an error worth
            // telling the customer's browser about.
            return new JsonResponse(['stage' => null]);
        }

        // The cart context is typed against Sylius' own OrderInterface; only our
        // own carts carry a vendor and a shipping address, which is everything
        // this is about.
        if (!$cart instanceof OrderInterface) {
            return new JsonResponse(['stage' => null]);
        }

        $stage = $this->precogTracker->ping($cart, (string) $page, $submitted);

        return new JsonResponse(['stage' => $stage?->value]);
    }

    /**
     * Every ghost currently in flight, for a dispatch board that has just loaded.
     *
     * Lives under /admin so it inherits the ROLE_DISPATCHER access rule that
     * already guards the board itself, rather than growing a second, separate
     * answer to the question of who may see this.
     */
    #[Route(path: '/admin/precog', name: 'admin_precog', methods: ['GET'])]
    public function listAction(): JsonResponse
    {
        return new JsonResponse(['precog' => $this->precogTracker->all()]);
    }
}
