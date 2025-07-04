<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\AccessControlTrait;
use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Controller\Utils\InjectAuthTrait;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\DeliveryForm;
use AppBundle\Entity\DeliveryFormSubmission;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Sylius\PricingRulesBasedPrice;
use AppBundle\Form\Checkout\CheckoutPayment;
use AppBundle\Form\Checkout\CheckoutPaymentType;
use AppBundle\Form\DeliveryEmbedType;
use AppBundle\Pricing\PricingManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Util\Canonicalizer as CanonicalizerInterface;
use Hashids\Hashids;
use libphonenumber\PhoneNumber;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/{_locale}', requirements: ['_locale' => '%locale_regex%'])]
class EmbedController extends AbstractController
{
    use AccessControlTrait;
    use InjectAuthTrait;
    use DeliveryTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RepositoryInterface $customerRepository,
        private readonly FactoryInterface $customerFactory,
        private readonly TranslatorInterface $translator,
        private readonly FormFactoryInterface $formFactory)
    {
    }

    protected function getDeliveryRoutes()
    {
        return [];
    }

    private function doCreateDeliveryForm(Request $request, bool $readRequest = true): FormInterface
    {
        $deliveryForm = $this->getDeliveryForm($request);

        $options = [
            'with_weight'      => $deliveryForm->getWithWeight(),
            'with_vehicle'     => $deliveryForm->getWithVehicle(),
            'with_time_slot'   => $deliveryForm->getTimeSlot(),
            'with_package_set' => $deliveryForm->getPackageSet(),
        ];

        $delivery = Delivery::create();

        if ($readRequest) {
            if ($d = $this->getDeliveryFromRequest($request)) {
                $delivery = $d;
            }
        }

        return $this->formFactory->createNamed('delivery', DeliveryEmbedType::class, $delivery, $options);
    }

    private function findOrCreateCustomer($email, PhoneNumber $telephone, CanonicalizerInterface $canonicalizer)
    {
        $customer = $this->customerRepository
            ->findOneBy([
                'emailCanonical' => $canonicalizer->canonicalize($email)
            ]);

        if (!$customer) {
            $customer = $this->customerFactory->createNew();

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
        $deliveryForm = $this->entityManager->getRepository(DeliveryForm::class)->find($id);

        if (null === $deliveryForm) {
            throw $this->createNotFoundException(sprintf('DeliveryForm #%d does not exist', $id));
        }

        return $deliveryForm;
    }

    #[Route(path: '/embed/delivery/start', name: 'embed_delivery_start_legacy')]
    public function deliveryStartLegacyAction()
    {
        $qb = $this->entityManager
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

    #[Route(path: '/forms/{hashid}', name: 'embed_delivery_start')]
    public function deliveryStartAction($hashid, Request $request,
        PricingManager $pricingManager,
        EntityManagerInterface $entityManager)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $form = $this->doCreateDeliveryForm($request);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            $price = $pricingManager->getPrice($delivery, $this->getPricingRuleSet($request));

            if (null === $price) {

                $message = $this->translator->trans('delivery.price.error.priceCalculation', [], 'validators');
                $form->addError(new FormError($message));

            } else  {

                $submission = new DeliveryFormSubmission();
                $submission->setDeliveryForm($this->getDeliveryForm($request));
                $submission->setData(serialize($request->request->get($form->getName())));
                $submission->setPrice($price);

                $entityManager->persist($submission);
                $entityManager->flush();

                $hashids = new Hashids($this->getParameter('secret'), 12);

                return $this->redirectToRoute('embed_delivery_summary', [
                    'hashid' => $hashid,
                    'data' => $hashids->encode($submission->getId()),
                ]);

            }
        }

        return $this->render('embed/delivery/start.html.twig', [
            'form' => $form->createView(),
            'hashid' => $hashid,
        ]);
    }

    #[Route(path: '/forms/{hashid}/summary', name: 'embed_delivery_summary')]
    public function deliverySummaryAction($hashid, Request $request,
        OrderRepository $orderRepository,
        OrderManager $orderManager,
        OrderFactory $orderFactory,
        PricingManager $pricingManager,
        EntityManagerInterface $objectManager,
        CanonicalizerInterface $canonicalizer,
        OrderProcessorInterface $orderProcessor)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $submission = $this->getDeliverySubmissionFromRequest($request);

        if (null === $submission) {

            return $this->redirectToRoute('embed_delivery_start', ['hashid' => $hashid]);
        }

        $form = $this->doCreateDeliveryForm($request);

        $formData = unserialize($submission->getData());

        $price = $submission->getPrice();

        $form->submit($formData);

        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            if ($request->isMethod('POST')) {

                $hashidsForOrder = new Hashids($this->getParameter('secret'), 16);

                if (!$request->query->has('order')) {

                    return $this->redirectToRoute('embed_delivery_start', ['hashid' => $hashid]);
                }

                $decoded = $hashidsForOrder->decode($request->query->get('order'));

                if (count($decoded) !== 1) {

                    return $this->redirectToRoute('embed_delivery_start', ['hashid' => $hashid]);
                }

                $orderId = current($decoded);

                $order = $orderRepository->findCartById($orderId);

                if (null === $order) {

                    return $this->redirectToRoute('embed_delivery_start', ['hashid' => $hashid]);
                }

                $checkoutPayment = new CheckoutPayment($order);
                $paymentForm = $this->createForm(CheckoutPaymentType::class, $checkoutPayment, [
                    'csrf_protection' => false,
                ]);

                $paymentForm->handleRequest($request);

                if ($paymentForm->isSubmitted() && $paymentForm->isValid()) {

                    $data = [
                        'stripeToken' => $paymentForm->get('stripePayment')->get('stripeToken')->getData()
                    ];

                    if ($paymentForm->has('paymentMethod')) {
                        $data['mercadopagoPaymentMethod'] = $paymentForm->get('paymentMethod')->getData();
                    }
                    if ($paymentForm->has('installments')) {
                        $data['mercadopagoInstallments'] = $paymentForm->get('installments')->getData();
                    }

                    if (null === $order->getDelivery()) {
                        $order->setDelivery($delivery);
                    }

                    // Keep a copy of the payments before trying authorization
                    $payments = $order->getPayments()->filter(
                        fn (PaymentInterface $payment): bool => $payment->getState() === PaymentInterface::STATE_CART);

                    $orderManager->checkout($order, $data);
                    $objectManager->flush();

                    $failedPayments = $payments->filter(
                        fn (PaymentInterface $payment): bool => $payment->getState() === PaymentInterface::STATE_FAILED);

                    if (count($failedPayments) > 0) {
                        $errors = $failedPayments->map(fn (PaymentInterface $payment): string => $payment->getLastError());
                        $error = implode("\n", $errors->toArray());

                        return $this->render('embed/delivery/summary.html.twig', [
                            'hashid' => $hashid,
                            'delivery' => $delivery,
                            'price' => $price,
                            'price_excluding_tax' => ($order->getTotal() - $order->getTaxTotal()),
                            'form' => $paymentForm->createView(),
                            'order' => $order,
                            'error' => $error,
                            'submission_hashid' => $request->query->get('data'),
                        ]);
                    }

                    $this->addFlash(
                        'embed_delivery',
                        $this->translator->trans('embed.delivery.confirm_message')
                    );

                    $hashids = new Hashids($this->getParameter('secret'), 8);

                    return $this->redirectToRoute('public_order', [
                        'hashid' => $hashids->encode($order->getId())
                    ]);
                } else {
                    return $this->render('embed/delivery/summary.html.twig', [
                        'hashid' => $hashid,
                        'delivery' => $delivery,
                        'price' => $price,
                        'price_excluding_tax' => ($order->getTotal() - $order->getTaxTotal()),
                        'form' => $paymentForm->createView(),
                        'order' => $order,
                        'submission_hashid' => $request->query->get('data'),
                    ]);
                }
            }

            $email     = $form->get('email')->getData();
            $telephone = $form->get('telephone')->getData();

            $customer = $this->findOrCreateCustomer($email, $telephone, $canonicalizer);
            $order    = $orderFactory->createForDelivery($delivery, $customer, false);
            $pricingManager->processDeliveryOrder($order, [$pricingManager->getCustomProductVariant($delivery, new PricingRulesBasedPrice($price))]);

            $checkoutPayment = new CheckoutPayment($order);
            $paymentForm = $this->createForm(CheckoutPaymentType::class, $checkoutPayment, [
                'csrf_protection' => false,
            ]);

            if ($billingAddress = $form->get('billingAddress')->getData()) {
                $this->setBillingAddress($order, $billingAddress);
            }

            $orderProcessor->process($order);

            $objectManager->persist($order);
            $objectManager->flush();

            return $this->render('embed/delivery/summary.html.twig', [
                'hashid' => $hashid,
                'delivery' => $delivery,
                'price' => $price,
                'price_excluding_tax' => ($order->getTotal() - $order->getTaxTotal()),
                'form' => $paymentForm->createView(),
                'order' => $order,
                'submission_hashid' => $request->query->get('data'),
            ]);
        }
    }

    private function setBillingAddress(OrderInterface $order, Address $address)
    {
        if (null !== $address->getCompany() || null !== $address->getStreetAddress()) {
            $order->setBillingAddress($address);
        }
    }

    private function getDeliveryForm(Request $request): ?DeliveryForm
    {
        return $this->decode($request->get('hashid'));
    }

    private function getDeliveryFromRequest(Request $request): ?Delivery
    {
        $submission = $this->getDeliverySubmissionFromRequest($request);

        if (null === $submission) {

            return null;
        }

        $form = $this->doCreateDeliveryForm($request, false);
        $formData = unserialize($submission->getData());

        $form->submit($formData);

        if ($form->isSubmitted() && $form->isValid()) {

            return $form->getData();
        }

        return null;
    }

    private function getPricingRuleSet(Request $request)
    {
        return $this->getDeliveryForm($request)->getPricingRuleSet();
    }

    private function getDeliverySubmissionFromRequest(Request $request): ?DeliveryFormSubmission
    {
        $hashids = new Hashids($this->getParameter('secret'), 12);

        if (!$request->query->has('data')) {

            return null;
        }

        $decoded = $hashids->decode($request->query->get('data'));

        if (count($decoded) !== 1) {

            return null;
        }

        $submissionId = current($decoded);

        return $this->entityManager
            ->getRepository(DeliveryFormSubmission::class)
            ->find($submissionId);
    }
}
