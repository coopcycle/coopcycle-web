<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\StripePayment;
use AppBundle\Entity\Task;
use AppBundle\Form\DeliveryEmbedType;
use AppBundle\Form\StripePaymentType;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Stripe;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class EmbedController extends Controller
{
    private function createDeliveryForm()
    {
        $delivery = Delivery::create();

        return $this->get('form.factory')->createNamed('delivery', DeliveryEmbedType::class, $delivery);
    }

    private function getPricingRuleSet()
    {
        $pricingRuleSet = null;
        try {
            $pricingRuleSetId = $this->get('craue_config')->get('embed.delivery.pricingRuleSet');
            if ($pricingRuleSetId) {
                $pricingRuleSet = $this->getDoctrine()
                    ->getRepository(PricingRuleSet::class)
                    ->find($pricingRuleSetId);
            }
        } catch (\RuntimeException $e) {}

        return $pricingRuleSet;
    }

    private function applyDistanceDuration(FormInterface $form)
    {
        $pickup   = $form->get('pickup')->getData();
        $dropoff  = $form->get('dropoff')->getData();
        $delivery = $form->getData();

        $coordinates = [];
        foreach ($delivery->getTasks() as $task) {
            $coordinates[] = $task->getAddress()->getGeo();
        }

        $data = $this->get('routing_service')->getServiceResponse('route', $coordinates, [
            'steps' => 'true',
            'overview' => 'full'
        ]);

        $distance = $data['routes'][0]['distance'];
        $duration = $data['routes'][0]['duration'];
        $polyline = $data['routes'][0]['geometry'];

        $delivery->setDistance((int) $distance);
        $delivery->setDuration((int) $duration);
        $delivery->setPolyline($polyline);
    }

    private function applyTaxes(Delivery $delivery, PricingRuleSet $pricingRuleSet)
    {
        $deliveryManager = $this->get('coopcycle.delivery.manager');

        $totalIncludingTax = $deliveryManager->getPrice($delivery, $pricingRuleSet);

        if (null === $totalIncludingTax) {
            throw new \Exception('Impossible de déterminer le prix de la livraison');
        }

        // FIXME This is deprecated
        $delivery->setPrice($totalIncludingTax);
        $delivery->setTotalIncludingTax($totalIncludingTax);

        $deliveryManager->applyTaxes($delivery);
    }

    /**
     * @Route("/embed/delivery/start", name="embed_delivery_start")
     * @Template
     */
    public function deliveryStartAction()
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $pricingRuleSet = $this->getPricingRuleSet();
        if (!$pricingRuleSet) {
            throw new NotFoundHttpException('Pricing rule set not configured');
        }

        $form = $this->createDeliveryForm();

        return $this->render('@App/Embed/Delivery/start.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/embed/delivery/summary", name="embed_delivery_summary")
     * @Template
     */
    public function deliverySummaryAction(Request $request)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $pricingRuleSet = $this->getPricingRuleSet();
        if (!$pricingRuleSet) {
            throw new NotFoundHttpException('Pricing rule set not configured');
        }

        $form = $this->createDeliveryForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            try {

                $delivery = $form->getData();

                $this->applyDistanceDuration($form);
                $this->applyTaxes($delivery, $pricingRuleSet);

                return $this->render('@App/Embed/Delivery/summary.html.twig', [
                    'form' => $form->createView(),
                ]);

            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }

        }

        return $this->render('@App/Embed/Delivery/start.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/embed/delivery/process", name="embed_delivery_process")
     * @Template
     */
    public function deliveryProcessAction(Request $request)
    {
        $notificationManager = $this->get('coopcycle.notification_manager');

        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $pricingRuleSet = $this->getPricingRuleSet();
        if (!$pricingRuleSet) {
            throw new NotFoundHttpException('Pricing rule set not configured');
        }

        $form = $this->createDeliveryForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();
            $email = $form->get('email')->getData();

            $this->applyDistanceDuration($form);
            $this->applyTaxes($delivery, $pricingRuleSet);

            $delivery->setStatus(Delivery::STATUS_TO_BE_CONFIRMED);

            $userManipulator = $this->get('fos_user.util.user_manipulator');
            $userManager = $this->get('fos_user.user_manager');

            $user = $userManager->findUserByEmail($email);
            if (!$user) {

                [ $localPart, $domain ] = explode('@', $email);
                $username = $this->get('slugify')->slugify($localPart, ['separator' => '_']);
                $password = random_bytes(16);

                $user = $userManipulator->create($username, $password, $email, true, false);
            }

            $this->getDoctrine()->getManagerForClass(Delivery::class)->persist($delivery);
            $this->getDoctrine()->getManagerForClass(Delivery::class)->flush();

            // Create a StripePayment & store it in database
            $stripePayment = StripePayment::create($user, $delivery);

            $this->getDoctrine()->getManagerForClass(StripePayment::class)->persist($stripePayment);
            $this->getDoctrine()->getManagerForClass(StripePayment::class)->flush();

            // Send confirmation email
            $email = $form->get('email')->getData();
            $notificationManager->notifyDeliveryToBeConfirmed($delivery, $email);

            $this->addFlash(
                'notice',
                $this->get('translator')->trans('embed.delivery.confirm_message')
            );

            return $this->redirectToRoute('embed_delivery', ['id' => $delivery->getId()]);
        }

        return $this->render('@App/Embed/Delivery/summary.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/embed/delivery/{id}", name="embed_delivery")
     * @Template
     */
    public function deliveryAction($id, Request $request)
    {
        $delivery = $this->getDoctrine()
            ->getRepository(Delivery::class)
            ->find($id);

        $stripePayment = $this->getDoctrine()->getRepository(StripePayment::class)
            ->findOneBy([
                'resourceClass' => ClassUtils::getClass($delivery),
                'resourceId' => $delivery->getId(),
            ]);

        return $this->render('@App/Embed/Delivery/delivery.html.twig', [
            'delivery' => $delivery,
            'stripe_payment' => $stripePayment,
        ]);
    }

    /**
     * @Route("/embed/payment/{uuid}", name="embed_payment")
     * @Template
     */
    public function paymentAction($uuid, Request $request)
    {
        $settingsManager = $this->get('coopcycle.settings_manager');

        Stripe\Stripe::setApiKey($settingsManager->get('stripe_secret_key'));

        $stripePayment = $this->getDoctrine()->getRepository(StripePayment::class)
            ->findOneByUuid($uuid);

        $resource = $this->getDoctrine()
            ->getRepository($stripePayment->getResourceClass())
            ->find($stripePayment->getResourceId());

        $chargeDescription = '';
        $chargeMetadata = [];
        if ($resource instanceof Delivery) {
            $chargeDescription = sprintf('Delivery #%d', $resource->getId());
            $chargeMetadata = [
                'delivery_id' => $resource->getId(),
            ];
        } else {
            throw new NotFoundHttpException(sprintf('No template for resource of class %s', $stripePayment->getResourceClass()));
        }

        $form = $this->createForm(StripePaymentType::class, $stripePayment);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $stripeToken = $form->get('stripeToken')->getData();

            $charge = Stripe\Charge::create([
              'amount' => $delivery->getTotalIncludingTax() * 100,
              'currency' => 'eur',
              'description' => $chargeDescription,
              'metadata' => $chargeMetadata,
              'source' => $stripeToken,
            ]);

            $stripePayment->setCharge($charge->id);
            $stripePayment->setStatus(StripePayment::STATUS_CAPTURED);

            $this->getDoctrine()->getManagerForClass(StripePayment::class)->flush();

            return $this->redirectToRoute('embed_payment', ['uuid' => $uuid]);
        }

        return $this->render('@App/Embed/StripePayment/form.html.twig', [
            'resource' => $resource,
            'stripe_payment' => $stripePayment,
            'form' => $form->createView(),
        ]);
    }
}
