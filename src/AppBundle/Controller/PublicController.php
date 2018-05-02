<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\StripePaymentType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stripe;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/{_locale}/pub", requirements={ "_locale": "%locale_regex%" })
 */
class PublicController extends Controller
{
    /**
     * @Route("/o/{number}", name="public_order")
     * @Template
     */
    public function orderAction($number, Request $request)
    {
        $settingsManager = $this->get('coopcycle.settings_manager');
        $stateMachineFactory = $this->get('sm.factory');

        Stripe\Stripe::setApiKey($settingsManager->get('stripe_secret_key'));

        $order = $this->get('sylius.repository.order')->findOneBy([
            'number' => $number
        ]);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order %s does not exist', $number));
        }

        $stripePayment = $order->getLastPayment();
        $stateMachine = $stateMachineFactory->get($stripePayment, PaymentTransitions::GRAPH);

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
        $order = $this->get('sylius.repository.order')->findOneBy([
            'number' => $number
        ]);

        if (null === $order) {
            throw new NotFoundHttpException(sprintf('Order %s does not exist', $number));
        }

        $delivery = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->findOneByOrder($order);

        $html = $this->renderView('@App/Pdf/delivery.html.twig', [
            'order' => $order,
            'delivery' => $delivery,
            'customer' => $order->getCustomer()
        ]);

        return new Response($this->get('knp_snappy.pdf')->getOutputFromHtml($html), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
