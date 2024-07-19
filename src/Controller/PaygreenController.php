<?php

namespace AppBundle\Controller;

use AppBundle\Service\PaygreenManager;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Paygreen\Sdk\Payment\V3\Model as PaygreenModel;
use Psr\Log\LoggerInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @see https://developers.paygreen.fr/reference/post_create_payment_order
 * @see https://developers.paygreen.fr/docs/payment#payment-orders
 */
class PaygreenController extends AbstractController
{
    public function __construct(
        private Hashids $hashids8,
        private EntityManagerInterface $entityManager,
        private PaygreenManager $paygreenManager,
        private LoggerInterface $logger)
    {}

    /**
     * @Route("/paygreen/create-payment-order/{hashId}", name="paygreen_create_payment_order")
     */
    public function createPaymentOrderAction($hashId)
    {
        try {
            [ $paymentOrderId, $objectSecret ] = $this->getViewData($hashId);

            return new JsonResponse([
                'id' => $paymentOrderId,
                'object_secret' => $objectSecret,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * @Route("/paygreen/{hashId}/webview", name="paygreen_webview")
     */
    public function webviewAction($hashId)
    {
        [ $paymentOrderId, $objectSecret ] = $this->getViewData($hashId);

        return $this->render('payment/paygreen_webview.html.twig', [
            'payment_order_id' => $paymentOrderId,
            'object_secret' => $objectSecret,
        ]);
    }

    private function getViewData($hashId): array
    {
        $decoded = $this->hashids8->decode($hashId);
        if (count($decoded) !== 1) {
            $this->logger->warning(sprintf('Payment with hash "%s" does not exist', $hashId));

            throw new \Exception(sprintf('Payment with hash "%s" does not exist', $hashId));
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {
            $this->logger->error(sprintf('Payment with id "%d" does not exist', $paymentId), ['hash' => $hashId]);

            throw new \Exception(sprintf('Payment with id "%d" does not exist', $paymentId));
        }

        /*
        $keys = ['id', 'object_secret'];
        $order = $payment->getOrder();

        // https://developers.paygreen.fr/docs/auth#create-a-bearer-access-token
        $response = $this->paygreenClient->authenticate();
        $data = json_decode($response->getBody()->getContents())->data;
        $this->paygreenClient->setBearer($data->token);

        $details = $payment->getDetails();
        if (isset($details['paygreen_payment_order_id'])) {
            $response = $this->paygreenClient->getPaymentOrder($details['paygreen_payment_order_id']);
            if ($response->getStatusCode() === 200) {

                $data = json_decode($response->getBody()->getContents(), true);

                if ($data['data']['status'] === 'payment_order.pending') {
                    $expiresAt = Carbon::parse($data['data']['expires_at'])->tz(date_default_timezone_get());

                    $isExpired = $expiresAt->isBefore(Carbon::now());

                    if (!$isExpired) {
                        return [
                            $data['data']['id'],
                            $details['paygreen_object_secret']
                        ];
                    }
                }
            }
        }

        $shippingAddress = $order->getShippingAddress();

        $address = new PaygreenModel\Address();
        $address->setStreetLineOne($shippingAddress->getStreetAddress());
        $address->setCity($shippingAddress->getAddressLocality());
        $address->setCountryCode(strtoupper($this->country));
        $address->setPostalCode($shippingAddress->getPostalCode());

        if (!$order->getCustomer()->hasPaygreenBuyerId()) {

            $fullNameParts = explode(' ', $order->getCustomer()->getFullName(), 2);
            $firstName = $fullNameParts[0] ?? '';
            $lastName = $fullNameParts[1] ?? '';

            $buyer = new PaygreenModel\Buyer();
            $buyer->setReference(sprintf('cus_%s', $this->hashids8->encode($order->getCustomer()->getId())));
            $buyer->setEmail($order->getCustomer()->getEmailCanonical());
            $buyer->setFirstName(!empty($firstName) ? $firstName : 'N/A');
            $buyer->setLastName(!empty($lastName) ? $lastName : 'N/A');
            $buyer->setBillingAddress($address);

            $response = $this->paygreenClient->createBuyer($buyer);
            $data = json_decode($response->getBody()->getContents(), true);

            $order->getCustomer()->setPaygreenBuyerId($data['data']['id']);
        }

        $buyer = new PaygreenModel\Buyer();
        $buyer->setId($order->getCustomer()->getPaygreenBuyerId());

        $this->orderNumberAssigner->assignNumber($order);

        $paymentOrder = new PaygreenModel\PaymentOrder();
        $paymentOrder->setReference(sprintf('ord_%s', $this->hashids8->encode($order->getId())));
        $paymentOrder->setBuyer($buyer);
        $paymentOrder->setAmount($order->getTotal());
        $paymentOrder->setAutoCapture(false);
        $paymentOrder->setCurrency($payment->getCurrencyCode());
        $paymentOrder->setShippingAddress($address);
        $paymentOrder->setDescription(sprintf('Order %s', $order->getNumber()));
        $paymentOrder->setShopId($order->getRestaurant()->getPaygreenShopId());

        // platforms is required when fees is set
        // Impossible to process fees on Payment Orders setup with non-wallet platforms.
        // You must only specify a platform with a wallet behavior (e.g. bank_card)
        // $paymentOrder->setPlatforms(['bank_card']);
        // $paymentOrder->setFees($order->getFeeTotal());

        $response = $this->paygreenClient->createPaymentOrder($paymentOrder);

        $data = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception($data['detail']);
        }

        $details = array_merge($details, [
            'paygreen_payment_order_id' => $data['data']['id'],
            'paygreen_object_secret' => $data['data']['object_secret'],
        ]);

        $payment->setDetails($details);
        */

        $this->paygreenManager->createPaymentOrder($payment);

        $this->entityManager->flush();

        return [
            $payment->getPaygreenPaymentOrderId(),
            $payment->getPaygreenObjectSecret()
        ];
    }
}
