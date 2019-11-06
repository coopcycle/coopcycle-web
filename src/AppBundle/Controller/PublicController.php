<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use Hashids\Hashids;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}/pub", requirements={ "_locale": "%locale_regex%" })
 */
class PublicController extends AbstractController
{
    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/o/{number}", name="public_order")
     * @Template
     */
    public function orderAction($number, Request $request)
    {
        $order = $this->orderRepository->findOneBy([
            'number' => $number
        ]);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order %s does not exist', $number));
        }

        if (null !== $order->getRestaurant()) {
            throw $this->createAccessDeniedException();
        }

        $lastPayment = $order->getLastPayment();

        return [
            'order' => $order,
            'last_payment' => $lastPayment,
        ];
    }

    /**
     * @Route("/d/{hashid}", name="public_delivery")
     * @Template
     */
    public function deliveryAction($hashid, Request $request,
        JWTEncoderInterface $jwtEncoder,
        JWSProviderInterface $jwsProvider)
    {
        $hashids = new Hashids($this->getParameter('secret'), 8);

        $decoded = $hashids->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $delivery = $this->getDoctrine()->getRepository(Delivery::class)->find($id);

        if (null === $delivery) {
            throw $this->createNotFoundException(sprintf('Delivery #%d does not exist', $id));
        }

        $courier = null;
        if ($delivery->isAssigned()) {
            $courier = $delivery->getPickup()->getAssignedCourier();
        }

        $token = null;
        if ($delivery->isAssigned() && !$delivery->isCompleted()) {

            $expiration = clone $delivery->getDropoff()->getDoneBefore();
            $expiration->modify('+3 hours');

            $token = $jwsProvider->create([
                // We add a custom "msn" claim to the token,
                // that will allow tracking a messenger
                'msn' => $courier->getUsername(),
                // Token expires 3 hours after expected completion
                'exp' => $expiration->getTimestamp(),
            ])->getToken();
        }

        return $this->render('@App/delivery/tracking.html.twig', [
            'delivery' => $delivery,
            'courier' => $courier,
            'token' => $token,
        ]);
    }
}
