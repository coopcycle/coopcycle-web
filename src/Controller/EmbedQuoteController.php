<?php

namespace AppBundle\Controller;

use AppBundle\Controller\Utils\DeliveryTrait;
use AppBundle\Entity\Address;
use AppBundle\Entity\Quote;
use AppBundle\Entity\QuoteForm;
use AppBundle\Entity\QuoteFormSubmission;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\Checkout\CheckoutPaymentType;
use AppBundle\Form\QuoteEmbedType;
use AppBundle\Form\StripePaymentType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderFactory;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Util\CanonicalizerInterface;
use Hashids\Hashids;
use libphonenumber\PhoneNumber;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class EmbedQuoteController extends AbstractController
{
    use DeliveryTrait;

    public function __construct(
        ManagerRegistry $doctrine,
        EntityManagerInterface $entityManager,
        RepositoryInterface $customerRepository,
        FactoryInterface $customerFactory,
        TranslatorInterface $translator)
    {
        $this->doctrine = $doctrine;
        $this->entityManager = $entityManager;
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->translator = $translator;
    }

    protected function getDeliveryRoutes()
    {
        return [];
    }

    private function doCreateQuoteForm(Request $request, bool $readRequest = true): FormInterface
    {
        $quoteForm = $this->getQuoteForm($request);

        $options = [
            'with_weight'      => $quoteForm->getWithWeight(),
            'with_vehicle'     => $quoteForm->getWithVehicle(),
            'with_time_slot'   => $quoteForm->getTimeSlot(),
            'with_package_set' => $quoteForm->getPackageSet(),
            //'data_class'       => QuoteForm::class,
        ];

        $quote = \AppBundle\Entity\Quote::create();

        if ($readRequest) {
            if ($d = $this->getQuoteFromRequest($request)) {
                $quote = $d;
            }
        }

        return $this->get('form.factory')->createNamed('delivery', QuoteEmbedType::class, $quote, $options);
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
        $quoteForm = $this->getDoctrine()->getRepository(QuoteForm::class)->find($id);

        if (null === $quoteForm) {
            throw $this->createNotFoundException(sprintf('QuoteForm #%d does not exist', $id));
        }

        return $quoteForm;
    }

    /**
     * @Route("/embed/quote/start", name="embed_quote_start_legacy")
     */
    public function quoteStartLegacyAction()
    {
        $qb = $this->getDoctrine()
            ->getRepository(QuoteForm::class)
            ->createQueryBuilder('df');

        $qb->orderBy('df.id', 'ASC');
        $qb->setMaxResults(1);

        $quoteForm = $qb->getQuery()->getOneOrNullResult();

        if (!$quoteForm) {
            throw $this->createNotFoundException();
        }

        $hashids = new Hashids($this->getParameter('secret'), 12);

        return $this->forward('AppBundle\Controller\EmbedQuoteController::quoteStartAction', [
            'hashid'  => $hashids->encode($quoteForm->getId()),
        ]);
    }

    /**
     * @Route("/forms_quote/{hashid}", name="embed_quote_start")
     */
    public function quoteStartAction($hashid, Request $request,
        DeliveryManager $deliveryManager,
        CanonicalizerInterface $canonicalizer,
        EntityManagerInterface $entityManager)
    {
        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $form = $this->doCreateQuoteForm($request);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $log = new Logger('quoteStartAction');
            $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream
            $log->warning('quoteStartAction - isSubmitted');
            try {

                $log->warning('quoteStartAction - isSubmitted $request->request->all()[\'delivery\'][\'pricingRuleSet\'] = '.json_encode($request->request->all()['delivery']));
                $quote = $form->getData();
                $log->warning('quoteStartAction - isSubmitted Testpoint 1');
                $price = $this->getQuotePrice(
                    $quote,
                    $this->doctrine->getRepository(PricingRuleSet::class)->find($request->request->all()['delivery']['pricingRuleSet']),
                    $deliveryManager
                );
                $log->warning('quoteStartAction - isSubmitted Testpoint 2');
                $submission = new QuoteFormSubmission();
                $log->warning('quoteStartAction - isSubmitted Testpoint 2.1');
                $submission->setQuoteForm($this->getQuoteForm($request));
                $log->warning('quoteStartAction - isSubmitted Testpoint 2.2');
                $submission->setData(serialize($request->request->get($form->getName())));
                $log->warning('quoteStartAction - isSubmitted Testpoint 2.3');
                $submission->setPrice($price);
                $log->warning('quoteStartAction - isSubmitted Testpoint 3');
                $entityManager->persist($submission);
                $entityManager->flush();
                $log->warning('quoteStartAction - isSubmitted Testpoint 4');
                $hashids = new Hashids($this->getParameter('secret'), 12);
                $log->warning('quoteStartAction - isSubmitted Testpoint 5');
                return $this->redirectToRoute('embed_quote_delivery_summary', [
                    'hashid' => $hashid,
                    'data' => $hashids->encode($submission->getId()),
                ]);

            //} catch (\Exception $e) {
            //    $log->warning('quoteStartAction - isSubmitted Exception: ' . $e);
            } catch (NoRuleMatchedException $e) {
                $log->warning('quoteStartAction - isSubmitted Exception:' . $e);
                $message = $this->translator->trans('delivery.price.error.priceCalculation', [], 'validators');
                $form->addError(new FormError($message));
            }

        }

        return $this->render('embed/delivery/quotestart.html.twig', [
            'form' => $form->createView(),
            'hashid' => $hashid,
        ]);
    }

    /**
     * @Route("/forms_quote/{hashid}/summary", name="embed_quote_delivery_summary")
     */
    public function quoteSummaryAction($hashid, Request $request,
        DeliveryManager $deliveryManager,
        OrderRepositoryInterface $orderRepository,
        OrderManager $orderManager,
        OrderFactory $orderFactory,
        EntityManagerInterface $objectManager,
        CanonicalizerInterface $canonicalizer,
        OrderProcessorInterface $orderProcessor)
    {

        
        $log = new Logger('quoteSummaryAction');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream
        $log->warning('quoteSummaryAction');


        if ($this->container->has('profiler')) {
            $this->container->get('profiler')->disable();
        }

        $submission = $this->getQuoteSubmissionFromRequest($request);

        if (null === $submission) {

            return $this->redirectToRoute('embed_quote_start', ['hashid' => $hashid]);
        }

        $form = $this->doCreateQuoteForm($request);

        $formData = unserialize($submission->getData());

        $price = $submission->getPrice();

        $form->submit($formData);

        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            if ($request->isMethod('POST')) {

                $hashidsForOrder = new Hashids($this->getParameter('secret'), 16);

                if (!$request->query->has('order')) {

                    return $this->redirectToRoute('embed_quote_start', ['hashid' => $hashid]);
                }

                $decoded = $hashidsForOrder->decode($request->query->get('order'));

                if (count($decoded) !== 1) {

                    return $this->redirectToRoute('embed_quote_start', ['hashid' => $hashid]);
                }

                $orderId = current($decoded);

                $order = $orderRepository->findCartById($orderId);

                if (null === $order) {

                    return $this->redirectToRoute('embed_quote_start', ['hashid' => $hashid]);
                }

                $paymentForm = $this->createForm(CheckoutPaymentType::class, $order, [
                    'csrf_protection' => false,
                ]);

                $paymentForm->handleRequest($request);

                if ($paymentForm->isSubmitted() && $paymentForm->isValid()) {

                    $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

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

                    $orderManager->checkout($order, $data);
                    $objectManager->flush();

                    if (PaymentInterface::STATE_FAILED === $payment->getState()) {
                        return $this->render('embed/delivery/quotesummary.html.twig', [
                            'hashid' => $hashid,
                            'delivery' => $delivery,
                            'price' => $price,
                            'price_excluding_tax' => ($order->getTotal() - $order->getTaxTotal()),
                            'form' => $paymentForm->createView(),
                            'payment' => $payment,
                            'order' => $order,
                            'error' => $payment->getLastError(),
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
                }
            }


            $email     = $form->get('email')->getData();
            $telephone = $form->get('telephone')->getData();

            $customer = $this->findOrCreateCustomer($email, $telephone, $canonicalizer);

            $log->warning('quoteSummaryAction - createOrderForQuote Test point');
            
            $order    = $this->createOrderForQuote($orderFactory, $delivery, $price, $customer = null, $attach = false);

            //$log->warning('quoteSummaryAction - createOrderForQuote' . $order);

            $paymentForm = $this->createForm(CheckoutPaymentType::class, $order, [
                'csrf_protection' => false,
            ]);

            $orderProcessor->process($order);

            $objectManager->persist($order);
            $objectManager->flush();

            $payment = $order->getLastPayment(PaymentInterface::STATE_CART);

            return $this->render('embed/delivery/quotesummary.html.twig', [
                'hashid' => $hashid,
                'delivery' => $delivery,
                'price' => $price,
                'price_excluding_tax' => ($order->getTotal() - $order->getTaxTotal()),
                'form' => $paymentForm->createView(),
                'payment' => $payment,
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

    private function getQuoteForm(Request $request): ?QuoteForm
    {
        return $this->decode($request->get('hashid'));
    }

    /**
     * @return Quote|null
     */
    private function getQuoteFromRequest(Request $request): ?Quote
    {
        $submission = $this->getQuoteSubmissionFromRequest($request);

        if (null === $submission) {

            return null;
        }

        $form = $this->doCreateQuoteForm($request, false);
        $formData = unserialize($submission->getData());

        $form->submit($formData);

        if ($form->isSubmitted() && $form->isValid()) {

            return $form->getData();
        }

        return null;
    }

    private function getPricingRuleSet(Request $request)
    {
        return $this->getQuoteForm($request)->getPricingRuleSet();
    }

    /**
     * @return QuoteFormSubmission|null
     */
    private function getQuoteSubmissionFromRequest(Request $request): ?QuoteFormSubmission
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
            ->getRepository(QuoteFormSubmission::class)
            ->find($submissionId);
    }
}
