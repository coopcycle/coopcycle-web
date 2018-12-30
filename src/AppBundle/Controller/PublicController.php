<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\StripePayment;
use AppBundle\Form\StripePaymentType;
use AppBundle\Service\SettingsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stripe;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}/pub", requirements={ "_locale": "%locale_regex%" })
 */
class PublicController extends AbstractController
{
    public function __construct(
        $stateMachineFactory,
        $orderRepository,
        $pdfGenerator)
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->orderRepository = $orderRepository;
        $this->pdfGenerator = $pdfGenerator;
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

        return new Response($this->pdfGenerator->getOutputFromHtml($html), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
