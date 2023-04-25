<?php

namespace AppBundle\Controller;

use AppBundle\Domain\Order\Event\CheckoutFailed;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\StripeAccount;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Service\EmailManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Hashids\Hashids;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Psr\Log\LoggerInterface;
use SimpleBus\SymfonyBridge\Bus\EventBus;
use Stripe;
use Stripe\Exception\ApiErrorException;
use Sylius\Component\Order\Factory\AdjustmentFactoryInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see https://stripe.com/docs/connect/standard-accounts
 */
class StripeController extends AbstractController
{
    private $secret;
    private $debug;
    private $entityManager;
    private $adjustmentFactory;
    private $logger;

    public function __construct(
        string $secret,
        bool $debug,
        EntityManagerInterface $entityManager,
        AdjustmentFactoryInterface $adjustmentFactory,
        LoggerInterface $logger)
    {
        $this->secret = $secret;
        $this->debug = $debug;
        $this->entityManager = $entityManager;
        $this->adjustmentFactory = $adjustmentFactory;
        $this->logger = $logger;
    }

    /**
     * @Route("/stripe/connect/standard", name="stripe_connect_standard_account")
     */
    public function connectStandardAccountAction(
        Request $request,
        JWTEncoderInterface $jwtEncoder,
        SettingsManager $settingsManager,
        UserManagerInterface $userManager)
    {
        if (!$request->query->has('state')) {
            throw $this->createAccessDeniedException();
        }

        $state = $request->query->get('state');

        try {
            $payload = $jwtEncoder->decode($state);
        } catch (JWTDecodeFailureException $e) {
            throw $this->createAccessDeniedException();
        }

        if (!isset($payload['iss']) || !isset($payload['slm'])) {
            throw $this->createAccessDeniedException();
        }

        $redirect = $payload['iss'];
        $livemode = filter_var($payload['slm'], FILTER_VALIDATE_BOOLEAN);

        $secretKey = $livemode ? $settingsManager->get('stripe_live_secret_key') : $settingsManager->get('stripe_test_secret_key');

        // curl https://connect.stripe.com/oauth/token \
        // -d client_secret=XXX \
        // -d code=AUTHORIZATION_CODE \
        // -d grant_type=authorization_code

        $params = array(
            'grant_type' => 'authorization_code',
            'code' => $request->query->get('code'),
            'client_secret' => $secretKey,
        );

        $req = curl_init('https://connect.stripe.com/oauth/token');
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($params));

        // TODO: Additional error handling
        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        $res = json_decode(curl_exec($req), true);
        curl_close($req);

        // Stripe returns a response containing the authentication credentials for the user:
        //
        // {
        //     "token_type": "bearer",
        //     "stripe_publishable_key": "{PUBLISHABLE_KEY}",
        //     "scope": "read_write",
        //     "livemode": false,
        //     "stripe_user_id": "{ACCOUNT_ID}",
        //     "refresh_token": "{REFRESH_TOKEN}",
        //     "access_token": "{ACCESS_TOKEN}"
        // }
        //
        // If there was a problem, we instead return an error:
        //
        // {
        //     "error": "invalid_grant",
        //     "error_description": "Authorization code does not exist: {AUTHORIZATION_CODE}"
        // }

        if (isset($res['error']) && !empty($res['error'])) {
            $this->addFlash(
                'error',
                $res['error_description']
            );

            return $this->redirectToRoute('homepage');
        }

        Stripe\Stripe::setApiKey($secretKey);
        Stripe\Stripe::setApiVersion(StripeManager::STRIPE_API_VERSION);

        $account = Stripe\Account::retrieve($res['stripe_user_id']);

        // FIXME Why is display_name empty sometimes?
        $displayName = !empty($account->display_name) ? $account->display_name : 'N/A';

        $stripeAccount = new StripeAccount();
        $stripeAccount
            ->setType($account->type)
            ->setDisplayName($displayName)
            ->setPayoutsEnabled($account->payouts_enabled)
            ->setStripeUserId($res['stripe_user_id'])
            ->setRefreshToken($res['refresh_token'])
            ->setLivemode($res['livemode'])
            ;

        $this->getUser()->addStripeAccount($stripeAccount);
        $userManager->updateUser($this->getUser());

        $this->addFlash('stripe_account', $stripeAccount->getId());

        return $this->redirect($redirect);
    }

    /**
     * @see https://stripe.com/docs/connect/webhooks
     *
     * @Route("/stripe/webhook", name="stripe_webhook", methods={"POST"})
     */
    public function webhookAction(Request $request,
        StripeManager $stripeManager,
        SettingsManager $settingsManager,
        OrderManager $orderManager,
        EventBus $eventBus,
        EmailManager $emailManager)
    {
        $this->logger->info('Received webhook');

        $stripeManager->configure();

        $payload = $request->getContent();

        $webhookSecret = $settingsManager->get('stripe_webhook_secret');
        $signature = $request->headers->get('stripe-signature');

        // Verify webhook signature and extract the event.
        // See https://stripe.com/docs/webhooks/signatures for more information.

        try {

            // Don't verify signature in debug mode,
            // to allow simple usage of Stripe CLI
            // @see https://stripe.com/docs/connect/webhooks#test-webhooks-locally
            if ($this->debug) {

                $data = json_decode($payload, true);
                $jsonError = json_last_error();
                if (null === $data && JSON_ERROR_NONE !== $jsonError) {
                    $msg = "Invalid payload: {$payload} "
                      . "(json_last_error() was {$jsonError})";

                    throw new Stripe\Exception\UnexpectedValueException($msg);
                }

                $event = Stripe\Event::constructFrom($data);
            } else {
                $event = Stripe\Webhook::constructEvent(
                    $payload, $signature, $webhookSecret
                );
            }

        } catch (Stripe\Exception\UnexpectedValueException $e) {

            $this->logger->error($e->getMessage());

            // Invalid payload.
            return new Response('', 400);
        } catch (Stripe\Exception\SignatureVerificationException $e) {

            $this->logger->error($e->getMessage());

            // Invalid Signature.
            return new Response('', 400);
        }

        if ($event->account) {
            $this->logger->info(sprintf('Received event of type "%s" from account "%s"', $event->type, $event->account));
        } else {
            $this->logger->info(sprintf('Received event of type "%s"', $event->type));
        }

        switch ($event->type) {
            case Stripe\Event::PAYMENT_INTENT_SUCCEEDED:
                return $this->handlePaymentIntentSucceeded($event, $orderManager);
            case Stripe\Event::PAYMENT_INTENT_PAYMENT_FAILED:
                return $this->handlePaymentIntentPaymentFailed($event, $eventBus, $emailManager);
            case Stripe\Event::CHARGE_CAPTURED:
                return $this->handleChargeCaptured($event, $stripeManager);
            case Stripe\Event::CHARGE_SUCCEEDED:
                return $this->handleChargeSucceeded($event);
        }

        return new Response('', 200);
    }

    /**
     * @param Stripe\PaymentIntent|string $paymentIntent
     * @return PaymentInterface|null
     */
    private function findOneByPaymentIntent($paymentIntent): ?PaymentInterface
    {
        $value = $paymentIntent instanceof Stripe\PaymentIntent ? $paymentIntent->id : $paymentIntent;

        $qb = $this->entityManager->getRepository(PaymentInterface::class)
            ->createQueryBuilder('p')
            ->andWhere('JSON_GET_FIELD_AS_TEXT(p.details, \'payment_intent\') = :payment_intent')
            ->setParameter('payment_intent', $value);

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function handlePaymentIntentSucceeded(Stripe\Event $event, OrderManager $orderManager): Response
    {
        $paymentIntent = $event->data->object;

        $this->logger->info(sprintf('Payment Intent has id "%s"', $paymentIntent->id));

        $payment = $this->findOneByPaymentIntent($paymentIntent);

        if (null === $payment) {
            $this->logger->error(sprintf('Payment Intent "%s" not found', $paymentIntent->id));

            return new Response('', 200);
        }

        $order = $payment->getOrder();

        // At the moment, we only manage successful intent via webhooks for Giropay
        if ($payment->isGiropay() && PaymentInterface::STATE_PROCESSING === $payment->getState()) {

            // FIXME
            // Here we should check if the time range is still realistic
            // We have no idea when the webhook is called actually

            $orderManager->checkout($order);
            $this->entityManager->flush();
        }

        return new Response('', 200);
    }

    private function handlePaymentIntentPaymentFailed(Stripe\Event $event, EventBus $eventBus, EmailManager $emailManager)
    {
        $paymentIntent = $event->data->object;

        $this->logger->info(sprintf('Payment Intent has id "%s"', $paymentIntent->id));

        $payment = $this->findOneByPaymentIntent($paymentIntent);

        if (null === $payment) {
            $this->logger->error(sprintf('Payment Intent "%s" not found', $paymentIntent->id));

            return new Response('', 200);
        }

        $order = $payment->getOrder();

        // At the moment, we only manage payment failed intent via webhooks for Giropay
        if ($payment->isGiropay() && PaymentInterface::STATE_PROCESSING === $payment->getState()) {

            // This will change payment state to "failed"
            $eventBus->handle(
                new CheckoutFailed($order, $payment, $paymentIntent->last_payment_error->message)
            );
            $this->entityManager->flush();

            $emailManager->sendTo(
                $emailManager->createOrderPaymentFailedMessage($order),
                sprintf('%s <%s>', $order->getCustomer()->getFullName(), $order->getCustomer()->getEmail())
            );
        }

        return new Response('', 200);
    }

    private function handleChargeCaptured(Stripe\Event $event, StripeManager $stripeManager): Response
    {
        $charge = $event->data->object;

        // Can happen when using Stripe CLI
        if (empty($charge->payment_intent)) {
            $this->logger->error(sprintf('Charge "%s" has no payment intent, skipping', $charge->id));

            return new Response('', 200);
        }

        $this->logger->info(sprintf('Retrieving payment intent "%s"', $charge->payment_intent));

        $payment = $this->findOneByPaymentIntent($charge->payment_intent);

        if (null === $payment) {
            $this->logger->error(sprintf('Payment Intent "%s" not found', $charge->payment_intent));

            return new Response('', 200);
        }

        if (!$event->account) {
            $stripeManager->createTransfersForHub($payment, $charge);
        }

        $stripeFee = $this->getStripeFee($event);

        $this->logger->info(sprintf('Stripe fee  = %d', $stripeFee));

        if ($stripeFee > 0) {
            $this->addStripeFeeAdjustment($payment->getOrder(), $stripeFee);
        }

        return new Response('', 200);
    }

    private function getStripeFee(Stripe\Event $event)
    {
        $charge = $event->data->object;

        $this->logger->info(sprintf('Retrieving balance transaction "%s" for charge "%s"',
            $charge->balance_transaction, $charge->id));

        $stripeOptions = [];
        if ($event->account) {
            $stripeOptions['stripe_account'] = $event->account;
        }

        $balanceTransaction =
            Stripe\BalanceTransaction::retrieve($charge->balance_transaction, $stripeOptions);

        $stripeFee = 0;
        foreach ($balanceTransaction->fee_details as $feeDetail) {
            if ('stripe_fee' === $feeDetail->type) {

                return $feeDetail->amount;
            }
        }

        return 0;
    }

    private function addStripeFeeAdjustment(OrderInterface $order, $stripeFee)
    {
        $order->removeAdjustments(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT);

        $stripeFeeAdjustment = $this->adjustmentFactory->createWithData(
            AdjustmentInterface::STRIPE_FEE_ADJUSTMENT,
            'Stripe fee',
            $stripeFee,
            $neutral = true
        );
        $order->addAdjustment($stripeFeeAdjustment);

        $this->entityManager->flush();
    }

    private function handleChargeSucceeded(Stripe\Event $event): Response
    {
        $charge = $event->data->object;

        // We handle this event *ONLY* if Giropay was used
        if ($charge->payment_method_details->type !== 'giropay') {

            return new Response('', 200);
        }

        $stripeFee = $this->getStripeFee($event);

        $this->logger->info(sprintf('Stripe fee  = %d', $stripeFee));

        if ($stripeFee > 0) {

            // Can happen when using Stripe CLI
            if (empty($charge->payment_intent)) {
                $this->logger->error(sprintf('Charge "%s" has no payment intent, skipping', $charge->id));

                return new Response('', 200);
            }

            $this->logger->info(sprintf('Retrieving payment intent "%s"', $charge->payment_intent));

            $payment = $this->findOneByPaymentIntent($charge->payment_intent);

            if (null === $payment) {
                $this->logger->error(sprintf('Payment Intent "%s" not found', $charge->payment_intent));

                return new Response('', 200);
            }

            $this->addStripeFeeAdjustment($payment->getOrder(), $stripeFee);
        }

        return new Response('', 200);
    }

    /**
     * @see https://stripe.com/docs/payments/accept-a-payment-synchronously#web-create-payment-intent
     *
     * @Route("/stripe/payment/{hashId}/create-intent", name="stripe_create_payment_intent", methods={"POST"})
     */
    public function createPaymentIntentAction($hashId, Request $request,
        OrderNumberAssignerInterface $orderNumberAssigner,
        StripeManager $stripeManager)
    {
        $hashids = new Hashids($this->secret, 8);

        $decoded = $hashids->decode($hashId);
        if (count($decoded) !== 1) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with hash "%s" does not exist', $hashId)]
            ], 400);
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with id "%d" does not exist', $paymentId)]
            ], 400);
        }

        $content = $request->getContent();

        $data = !empty($content) ? json_decode($content, true) : [];

        if (!isset($data['payment_method_id'])) {

            return new JsonResponse(['error' =>
                ['message' => 'No payment_method_id key found in request']
            ], 400);
        }

        $order = $payment->getOrder();

        // Assign order number now because it is needed for Stripe
        $orderNumberAssigner->assignNumber($order);

        $stripeManager->configure();

        try {

            $payment->setPaymentMethod($data['payment_method_id']);

            $savePaymentMethod = isset($data['save_payment_method']) ? $data['save_payment_method'] : false;

            $intent = $stripeManager->createIntent($payment, $savePaymentMethod);

            $payment->setPaymentIntent($intent);

            $this->entityManager->flush();

        } catch (ApiErrorException $e) {

            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }

        $this->logger->info(
            sprintf('Order #%d | Created payment intent %s', $order->getId(), $payment->getPaymentIntent())
        );

        $response = [];

        if ($payment->requiresUseStripeSDK()) {

            $this->logger->info(
                sprintf('Order #%d | Payment Intent requires action "%s"', $order->getId(), $payment->getPaymentIntentNextAction())
            );

            $response = [
                'requires_action' => true,
                'payment_intent_client_secret' => $payment->getPaymentIntentClientSecret()
            ];

        // When the status is "succeeded", it means we captured automatically
        // When the status is "requires_capture", it means we separated authorization and capture
        } elseif ('succeeded' === $payment->getPaymentIntentStatus() || $payment->requiresCapture()) {

            $this->logger->info(
                sprintf('Order #%d | Payment Intent status is "%s"', $order->getId(), $payment->getPaymentIntentStatus())
            );

            // The payment didn’t need any additional actions and completed!
            // Handle post-payment fulfillment
            $response = [
                'requires_action' => false,
                'payment_intent' => $payment->getPaymentIntent()
            ];

        } else {

            return new JsonResponse(['error' =>
                ['message' => 'Invalid PaymentIntent status']
            ], 400);
        }

        return new JsonResponse($response);
    }

    /**
     * @see https://stripe.com/docs/connect/cloning-customers-across-accounts
     *
     * @Route("/stripe/payment/{hashId}/clone-payment-method", name="stripe_clone_payment_method", methods={"POST"})
     */
    public function clonePaymentMethodToConnectedAccountAction($hashId, Request $request,
        StripeManager $stripeManager)
    {
        $hashids = new Hashids($this->secret, 8);

        $decoded = $hashids->decode($hashId);
        if (count($decoded) !== 1) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with hash "%s" does not exist', $hashId)]
            ], 400);
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with id "%d" does not exist', $paymentId)]
            ], 400);
        }

        $content = $request->getContent();

        $data = !empty($content) ? json_decode($content, true) : [];

        if (!isset($data['payment_method_id'])) {

            return new JsonResponse(['error' =>
                ['message' => 'No payment_method_id key found in request']
            ], 400);
        }

        $stripeManager->configure();

        try {
            $payment->setPaymentMethod($data['payment_method_id']);

            $clonedPaymentMethod = $stripeManager->clonePaymentMethodToConnectedAccount($payment);

            $this->entityManager->flush();
        } catch (ApiErrorException $e) {
            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }

        $response = [
            'cloned_payment_method' => $clonedPaymentMethod
        ];

        return new JsonResponse($response);
    }

    /**
     * @see https://stripe.com/docs/api/payment_methods/attach
     *
     * To attach a new PaymentMethod to a customer for future payments, Stripe recommends to use a SetupIntent
     * But if SetupIntent requires an extra action we'll attach the payment method to the customer later
     *
     * @Route("/stripe/payment/{hashId}/create-setup-intent-or-attach-pm", name="stripe_create_setup_intent_or_attach_pm", methods={"POST"})
     */
    public function createSetupIntentOrAttachPMAction($hashId, Request $request,
        StripeManager $stripeManager)
    {
        $hashids = new Hashids($this->secret, 8);

        $decoded = $hashids->decode($hashId);
        if (count($decoded) !== 1) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with hash "%s" does not exist', $hashId)]
            ], 400);
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with id "%d" does not exist', $paymentId)]
            ], 400);
        }

        $content = $request->getContent();

        $data = !empty($content) ? json_decode($content, true) : [];

        if (!isset($data['payment_method_to_save'])) {

            return new JsonResponse(['error' =>
                ['message' => 'No payment_method_to_save key found in request']
            ], 400);
        }

        $stripeManager->configure();

        try {

            $intent = $stripeManager->createSetupIntent($payment, $data['payment_method_to_save']);

            // if payment method requires some extra action we can not save the payment method through the SetupIntent
            // because we want to avoid request an extra action to the client now
            if ($intent->status === 'requires_action') {
                // in this case we save the payment method data and when the payment is finally authorised
                // $gateway->authorize() we'll attach it to the customer
                $payment->setPaymentDataToSaveAndReuse($data['payment_method_to_save']);

                $this->entityManager->flush();
            }

        } catch (ApiErrorException $e) {

            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }

        return new JsonResponse();
    }

    /**
     * @Route("/stripe/customer/{hashId}/payment-methods", name="stripe_customer_payment_methods", methods={"GET"})
     */
    public function customerPaymentMethodsActions($hashId, StripeManager $stripeManager)
    {
        $hashids = new Hashids($this->secret, 8);

        $decoded = $hashids->decode($hashId);
        if (count($decoded) !== 1) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Customer with hash "%s" does not exist', $hashId)]
            ], 400);
        }

        $customerId = current($decoded);

        $customer = $this->entityManager
            ->getRepository(Customer::class)
            ->find($customerId);

        if (null === $customer) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Customer with id "%d" does not exist', $customerId)]
            ], 400);
        }

        $user = $customer->getUser();

        if (null === $user->getStripeCustomerId()) {
            return new JsonResponse(['cards' => []]);
        }

        $stripeManager->configure();

        try {
            $cards = $stripeManager->getCustomerPaymentMethods($user->getStripeCustomerId());
        } catch (ApiErrorException $e) {
            return new JsonResponse(['cards' => []]);
        }

        $response = [
            'cards' => $cards
        ];

        return new JsonResponse($response);
    }

    /**
     * @see https://stripe.com/docs/payments/giropay/accept-a-payment#create-payment-intent
     *
     * @Route("/stripe/payment/{hashId}/giropay/create-intent", name="stripe_create_giropay_payment_intent", methods={"POST"})
     */
    public function createGiropayPaymentIntentAction($hashId, Request $request,
        OrderNumberAssignerInterface $orderNumberAssigner,
        StripeManager $stripeManager)
    {
        $hashids = new Hashids($this->secret, 8);

        $decoded = $hashids->decode($hashId);
        if (count($decoded) !== 1) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with hash "%s" does not exist', $hashId)]
            ], 400);
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with id "%d" does not exist', $paymentId)]
            ], 400);
        }

        $order = $payment->getOrder();

        // Assign order number now because it is needed for Stripe
        $orderNumberAssigner->assignNumber($order);

        try {

            $payment->setState(PaymentInterface::STATE_PROCESSING);
            $payment->setPaymentMethodTypes(['giropay']);

            $intent = $stripeManager->createGiropayIntent($payment);
            $payment->setPaymentIntent($intent);

            $this->entityManager->flush();

        } catch (ApiErrorException $e) {

            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }

        $this->logger->info(
            sprintf('Order #%d | Created payment intent %s', $order->getId(), $payment->getPaymentIntent())
        );

        $returnUrl = $this->generateUrl('payment_confirm', [
            'hashId' => $hashids->encode($payment->getId()),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse([
            'payment_intent_client_secret' => $payment->getPaymentIntentClientSecret(),
            'return_url' => $returnUrl,
        ]);
    }
}
