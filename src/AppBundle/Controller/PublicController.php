<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\StripePaymentType;
use AppBundle\Service\SettingsManager;
use Hashids\Hashids;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stripe;
use Sylius\Component\Payment\PaymentTransitions;
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
    public function __construct(
        $stateMachineFactory,
        $orderRepository)
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/o/{number}", name="public_order")
     * @Template
     */
    public function orderAction($number, Request $request, SettingsManager $settingsManager)
    {
        Stripe\Stripe::setApiKey($settingsManager->get('stripe_secret_key'));

        $order = $this->orderRepository->findOneBy([
            'number' => $number
        ]);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order %s does not exist', $number));
        }

        $stripePayment = $order->getLastPayment();
        $stateMachine = $this->stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);

        $parameters = [
            'order' => $order,
            'stripe_payment' => $stripePayment,
        ];

        if ($stateMachine->can(PaymentTransitions::TRANSITION_COMPLETE)) {

            $form = $this->createForm(StripePaymentType::class, $stripePayment);

            $form->handleRequest($request);

            // TODO : handle this with orderManager
            if ($form->isSubmitted() && $form->isValid()) {

                try {

                    $stripeToken = $form->get('stripeToken')->getData();

                    $charge = Stripe\Charge::create([
                      'amount' => $stripePayment->getAmount(),
                      'currency' => strtolower($stripePayment->getCurrencyCode()),
                      'description' => sprintf('Order %s', $order->getNumber()),
                      'metadata' => [
                        'order_id' => $order->getId()
                      ],
                      'source' => $stripeToken,
                    ]);

                    $stripePayment->setCharge($charge->id);
                    $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);

                } catch (\Exception $e) {

                    $stripePayment->setLastError($e->getMessage());
                    $stateMachine->apply(PaymentTransitions::TRANSITION_FAIL);

                } finally {
                    $this->getDoctrine()->getManagerForClass(StripePayment::class)->flush();
                }

                return $this->redirectToRoute('public_order', ['number' => $number]);
            }

            $parameters = array_merge($parameters, ['form' => $form->createView()]);
        }

        return $parameters;
    }

    /**
     * @Route("/i/{number}", name="public_invoice")
     * @Template
     */
    public function invoiceAction($number, Request $request)
    {
        $order = $this->orderRepository->findOneBy([
            'number' => $number
        ]);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order %s does not exist', $number));
        }

        $delivery = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->findOneByOrder($order);

        $html = $this->renderView('@App/pdf/delivery.html.twig', [
            'order' => $order,
            'delivery' => $delivery,
            'customer' => $order->getCustomer()
        ]);

        $httpClient = $this->get('csa_guzzle.client.browserless');

        $response = $httpClient->request('POST', '/pdf', ['json' => ['html' => $html]]);

        // TODO Check status

        return new Response((string) $response->getBody(), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @Route("/d/{hashid}", name="public_delivery")
     * @Template
     */
    public function deliveryAction($hashid, Request $request,
        SettingsManager $settingsManager,
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
