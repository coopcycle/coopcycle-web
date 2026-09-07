<?php

namespace AppBundle\Payment;

use AppBundle\Edenred\Authentication as EdenredAuthentication;
use AppBundle\Edenred\Client as EdenredClient;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Service\PaygreenManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Webmozart\Assert\Assert;

final class PaymentMethodsResolver
{
    public function __construct(
        private SettingsManager $settingsManager,
        private GatewayResolver $gatewayResolver,
        private PaygreenManager $paygreenManager,
        private PaymentMethodRepositoryInterface $paymentMethodRepository,
        private EdenredAuthentication $edenredAuthentication,
        private EdenredClient $edenredClient,
        private bool $cashEnabled,
        private bool $edenredEnabled
    ) {
    }

    /**
     * @return list<string> Lowercase method types (ex. "card", "cash_on_delivery")
     */
    public function resolveForApi(OrderInterface $order): array
    {
        $types = [];

        if ($this->supportsCard($order)) {
            $types[] = 'card';
        }

        if ($this->supportsCashOnDelivery($order)) {
            $types[] = 'cash_on_delivery';
        }

        if ($this->supportsEdenred($order)) {
            $types[] = 'edenred';
        }

        foreach ($this->resolvePaygreenTypes($order, includeWallets: true) as $type) {
            $types[] = $type;
        }

        return $this->filterAvailableTypes($order, $types);
    }

    /**
     * @return list<ResolvedPaymentMethod> Payment methods for the web checkout
     */
    public function resolveForCheckout(OrderInterface $order): array
    {
        $methods = [];

        if ($this->supportsCard($order)) {
            $methods[] = new ResolvedPaymentMethod('card');
        }

        $edenredMethod = $this->resolveEdenredForCheckout($order);
        if (null !== $edenredMethod) {
            $methods[] = $edenredMethod;
        }

        foreach ($this->resolvePaygreenTypes($order, includeWallets: false) as $type) {
            $methods[] = new ResolvedPaymentMethod($type);
        }

        if ($this->supportsCashOnDelivery($order)) {
            $methods[] = new ResolvedPaymentMethod('cash_on_delivery');
        }

        // Filter out methods that cannot be selected server-side (missing/disabled PaymentMethod)
        return array_values(array_filter($methods, function (ResolvedPaymentMethod $method) use ($order): bool {
            return $this->isSelectableType($order, $method->getType());
        }));
    }

    public function isAvailableForCheckout(OrderInterface $order, string $type): bool
    {
        $type = strtolower($type);

        // Optimize for the common case: the selection endpoint calls this on every click,
        // but resolving all methods can involve external calls (Edenred).
        switch ($type) {
            case 'card':
                return $this->supportsCard($order) && $this->isSelectableType($order, $type);
            case 'cash_on_delivery':
                return $this->supportsCashOnDelivery($order) && $this->isSelectableType($order, $type);
            case 'edenred':
                return null !== $this->resolveEdenredForCheckout($order) && $this->isSelectableType($order, $type);
            case 'restoflash':
            case 'conecs':
            case 'swile':
                return in_array($type, $this->resolvePaygreenTypes($order, includeWallets: false), true)
                    && $this->isSelectableType($order, $type);
        }

        return false;
    }

    private function supportsCard(OrderInterface $order): bool
    {
        if (!$this->settingsManager->supportsCardPayments()) {
            return false;
        }

        // When using pawaPay, credit card is only available if enabled at restaurant level.
        // @see src/Form/Checkout/CheckoutPaymentType.php (historical behavior)
        if ('pawapay' === $this->gatewayResolver->resolveForOrder($order)) {
            $restaurant = $order->getRestaurant();
            if (null !== $restaurant && !$restaurant->isPawapayEnabled()) {
                return false;
            }
        }

        return true;
    }

    private function supportsCashOnDelivery(OrderInterface $order): bool
    {
        return $this->cashEnabled || $order->supportsCashOnDelivery();
    }

    private function supportsEdenred(OrderInterface $order): bool
    {
        return $this->edenredEnabled && $order->supportsEdenred();
    }

    /**
     * @return list<string>
     */
    private function resolvePaygreenTypes(OrderInterface $order, bool $includeWallets): array
    {
        if ($order->isMultiVendor()) {
            return [];
        }

        if ('paygreen' !== $this->gatewayResolver->resolveForOrder($order)) {
            return [];
        }

        $restaurant = $order->getRestaurant();
        if (null === $restaurant) {
            return [];
        }

        $types = [];
        $platforms = $this->paygreenManager->getEnabledPlatforms($restaurant->getPaygreenShopId());

        foreach (['restoflash', 'conecs', 'swile'] as $type) {
            if (in_array($type, $platforms)) {
                $types[] = $type;
            }
        }

        if ($includeWallets) {
            foreach (['google_pay', 'apple_pay'] as $type) {
                if (in_array($type, $platforms)) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    private function resolveEdenredForCheckout(OrderInterface $order): ?ResolvedPaymentMethod
    {
        if (!$this->supportsEdenred($order)) {
            return null;
        }

        $customer = $order->getCustomer();
        $hasValidCredentials = false;

        if (null !== $customer) {
            Assert::isInstanceOf($customer, Customer::class);
            $hasValidCredentials = $this->edenredClient->hasValidCredentials($customer);

            // If connected, only show Edenred when some amount can be paid
            if ($hasValidCredentials) {
                $edenredAmount = $this->edenredClient->getMaxAmount($order);
                if ($edenredAmount <= 0) {
                    return null;
                }
            }
        }

        // This will be converted to `dataset.edenredIsConnected` / `dataset.edenredAuthorizeUrl` in JS.
        $choiceAttr = [
            'data-edenred-is-connected' => $hasValidCredentials,
            'data-edenred-authorize-url' => $this->edenredAuthentication->getAuthorizeUrl($order),
        ];

        return new ResolvedPaymentMethod('edenred', $choiceAttr);
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    private function filterAvailableTypes(OrderInterface $order, array $types): array
    {
        $types = array_values(array_unique($types));

        return array_values(array_filter($types, function (string $type) use ($order): bool {
            return $this->isSelectableType($order, $type);
        }));
    }

    private function isSelectableType(OrderInterface $order, string $type): bool
    {
        $code = strtoupper($type);

        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => $code]);

        if (null === $paymentMethod) {
            return false;
        }

        // The "CASH_ON_DELIVERY" payment method may not be enabled,
        // however if it's enabled at restaurant level, it is allowed.
        if ('cash_on_delivery' === $type && $order->supportsCashOnDelivery()) {
            return true;
        }

        return $paymentMethod->isEnabled();
    }
}
