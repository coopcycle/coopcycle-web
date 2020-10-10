<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\Checkout\CheckoutPaymentType;
use AppBundle\Form\DeliveryEmbedType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Util\CanonicalizerInterface;
use Hashids\Hashids;
use libphonenumber\PhoneNumber;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class EmbedController extends Controller
{
    use DeliveryTrait;

    protected function getDeliveryRoutes()
    {
        return [];
    }

    private function doCreateDeliveryForm(array $options = [])
    {
        $delivery = Delivery::create();

        return $this->get('form.factory')->createNamed('delivery', DeliveryEmbedType::class, $delivery, $options);
    }

    private function findOrCreateCustomer($email, PhoneNumber $telephone, CanonicalizerInterface $canonicalizer)
    {
        $customer = $this->get('sylius.repository.customer')
            ->findOneBy([
                'emailCanonical' => $canonicalizer->canonicalize($email)
            ]);

        if (!$customer) {
            $customer = $this->get('sylius.factory.customer')->createNew();

            $customer->setEmail($email);
            $customer->setEmailCanonical($canonicalizer->canonicalize($email));
        }

        // Make sure to use setTelephone(),
        // so that it converts PhoneNumber to ISO string
        $customer->setTelephone($telephone);

        return $customer;
    }

    private function decode($hashid)
    {
        $hashids = new Hashids($this->getParameter('secret'), 12);

        $decoded = $hashids->decode($hashid);

        if (count($decoded) !== 1) {
            throw new BadRequestHttpException(sprintf('Hashid "%s" could not be decoded', $hashid));
        }

        $id = current($decoded);
        $deliveryForm = $this->getDoctrine()->getRepository(DeliveryForm::class)->find($id);

        if (null === $deliveryForm) {
            throw $this->createNotFoundException(sprintf('DeliveryForm #%d does not exist', $id));
        }

        return $deliveryForm;
    }

    /**
     * @Route("/embed/delivery/start", name="embed_delivery_start_legacy")
     */
    public function deliveryStartLegacyAction()
    {
        $qb = $this->getDoctrine()
            ->getRepository(DeliveryForm::class)
            ->createQueryBuilder('df');

        $qb->orderBy('df.id', 'ASC');
        $qb->setMaxResults(1);

        $deliveryForm = $qb->getQuery()->getOneOrNullResult();

        if (!$deliveryForm) {
            throw $this->createNotFoundException();
        }

        $hashids = new Hashids($this->getParameter('secret'), 12);

        return $this->forward('AppBundle\Controller\EmbedController::deliveryStartAction', [
            'hashid'  => $hashids->encode($deliveryForm->getId()),
        ]);
    }

    /**
     * @Route("/forms/{hashid}", name="embed_delivery_start")
     */
    public function deliveryStartAction($hashid, Request $request)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $deliveryForm = $this->decode($hashid);

        $form = $this->doCreateDeliveryForm([
            'with_weight' => $deliveryForm->getWithWeight(),
            'with_vehicle' => $deliveryForm->getWithVehicle(),
            'with_time_slot' => $deliveryForm->getTimeSlot(),
            'with_package_set' => $deliveryForm->getPackageSet(),
        ]);
        $form->handleRequest($request);

        return $this->render('embed/delivery/start.html.twig', [
            'form' => $form->createView(),
            'hashid' => $hashid,
        ]);
    }

    /**
     * @Route("/forms/{hashid}/summary", name="embed_delivery_summary")
     */
    public function deliverySummaryAction($hashid, Request $request,
        DeliveryManager $deliveryManager,
        TaxRateResolverInterface $taxRateResolver)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $deliveryForm = $this->decode($hashid);

        $form = $this->doCreateDeliveryForm([
            'with_weight' => $deliveryForm->getWithWeight(),
            'with_vehicle' => $deliveryForm->getWithVehicle(),
            'with_time_slot' => $deliveryForm->getTimeSlot(),
            'with_package_set' => $deliveryForm->getPackageSet(),
            'with_payment' => true,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            try {

                $delivery = $form->getData();
                $price = $this->getDeliveryPrice(
                    $delivery,
                    $deliveryForm->getPricingRuleSet(),
                    $deliveryManager
                );

                $rate = $taxRateResolver->resolve(
                    $this->get('sylius.factory.product_variant')->createForDelivery($delivery, $price)
                );

                $priceExcludingTax = (int) round($price / (1 + $rate->getAmount()));

                return $this->render('embed/delivery/summary.html.twig', [
                    'hashid' => $hashid,
                    'price' => $price,
                    'price_excluding_tax' => $priceExcludingTax,
                    'form' => $form->createView(),
                ]);

            } catch (NoRuleMatchedException $e) {
                $message = $this->get('translator')->trans('delivery.price.error.priceCalculation', [], 'validators');
                $form->addError(new FormError($message));
            }

        }

        return $this->render('embed/delivery/start.html.twig', [
            'form' => $form->createView(),
            'hashid' => $hashid,
        ]);
    }

    /**
     * @Route("/forms/{hashid}/process", name="embed_delivery_process")
     */
    public function deliveryProcessAction($hashid, Request $request,
        OrderRepositoryInterface $orderRepository,
        OrderManager $orderManager,
        EntityManagerInterface $objectManager,
        DeliveryManager $deliveryManager,
        CanonicalizerInterface $canonicalizer)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $deliveryForm = $this->decode($hashid);

        $form = $this->doCreateDeliveryForm([
            'with_weight' => $deliveryForm->getWithWeight(),
            'with_vehicle' => $deliveryForm->getWithVehicle(),
            'with_time_slot' => $deliveryForm->getTimeSlot(),
            'with_package_set' => $deliveryForm->getPackageSet(),
            'with_payment' => true,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            $stripeToken = $form->get('stripePayment')->get('stripeToken')->getData();

            $email = $form->get('email')->getData();
            $telephone = $form->get('telephone')->getData();

            $customer = $this->findOrCreateCustomer($email, $telephone, $canonicalizer);
            $price = $this->getDeliveryPrice(
                $delivery,
                $deliveryForm->getPricingRuleSet(),
                $deliveryManager
            );
            $order = $this->createOrderForDelivery($delivery, $price, $customer);

            if ($billingAddress = $form->get('billingAddress')->getData()) {
                $this->setBillingAddress($order, $billingAddress);
            }

            $orderRepository->add($order);
            $orderManager->checkout($order, $stripeToken);
            $objectManager->flush();

            $this->addFlash(
                'embed_delivery',
                $this->get('translator')->trans('embed.delivery.confirm_message')
            );

            return $this->redirectToRoute('public_order', ['number' => $order->getNumber()]);
        }

        return $this->render('embed/delivery/summary.html.twig', [
            'form' => $form->createView(),
            'hashid' => $hashid,
        ]);
    }

    private function setBillingAddress(OrderInterface $order, Address $address)
    {
        if (null !== $address->getCompany() || null !== $address->getStreetAddress()) {
            $order->setBillingAddress($address);
        }
    }
}
