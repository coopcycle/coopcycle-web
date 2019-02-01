<?php

namespace AppBundle\Form;

use AppBundle\Entity\Restaurant;
use AppBundle\Service\SettingsManager;
use League\Csv\Writer as CsvWriter;
use Stripe;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type as FormType;

class OrdersExportType extends AbstractType
{
    private $orderRepository;
    private $settingsManager;
    private $stripeLiveMode;
    private $stripeOptionsByRestaurant = [];
    private $balanceTransactionsByRestaurant = [];

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SettingsManager $settingsManager)
    {
        $this->orderRepository = $orderRepository;
        $this->settingsManager = $settingsManager;
        $this->stripeLiveMode = $settingsManager->isStripeLivemode();

        Stripe\Stripe::setApiKey($settingsManager->get('stripe_secret_key'));
    }

    private function getStripeFee(Restaurant $restaurant, PaymentInterface $payment, $start, $end)
    {
        if (!isset($this->stripeOptionsByRestaurant[$restaurant->getId()])) {
            $restaurantPaysStripeFee =
                $restaurant->getContract()->isRestaurantPaysStripeFee();

            $stripeOptions = [];
            if ($restaurantPaysStripeFee) {
                $stripeAccount = $restaurant->getStripeAccount($this->stripeLiveMode);
                $stripeOptions['stripe_account'] = $stripeAccount->getStripeUserId();
            }

            $this->stripeOptionsByRestaurant[$restaurant->getId()] = $stripeOptions;
        }

        $stripeOptions = $this->stripeOptionsByRestaurant[$restaurant->getId()];

        // To avoid making lots of API calls, we retrieve all the balance transactions at once
        if (!isset($this->balanceTransactionsByRestaurant[$restaurant->getId()])) {

            $result = Stripe\BalanceTransaction::all([
                'limit' => 100, // FIXME There may be more
                'created' => [
                    'gte' => $start->getTimestamp(),
                    'lte' => $end->getTimestamp(),
                ]
            ], $stripeOptions);

            $balanceTransactionsByCharge = [];
            foreach ($result->data as $balanceTransaction) {
                $balanceTransactionsByCharge[$balanceTransaction->source] =
                    $balanceTransaction;
            }

            $this->balanceTransactionsByRestaurant[$restaurant->getId()] =
                $balanceTransactionsByCharge;
        }

        $balanceTransactions = $this->balanceTransactionsByRestaurant[$restaurant->getId()];

        if (isset($balanceTransactions[$payment->getCharge()])) {

            $balanceTransaction = $balanceTransactions[$payment->getCharge()];

            foreach ($balanceTransaction->fee_details as $feeDetail) {
                if ('stripe_fee' === $feeDetail->type) {

                    return $feeDetail->amount;
                }
            }

        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $firstDayOfThisMonth = new \DateTime('first day of this month');

        $builder
            ->add('start', DateType::class, [
                'label' => 'form.orders_export.start.label',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'data' => $firstDayOfThisMonth,
            ])
            ->add('end', DateType::class, [
                'label' => 'form.orders_export.end.label',
                'widget' => 'single_text',
                'format' => 'yyyy-MM-dd',
                'data' => new \DateTime(),
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $data = $event->getData();

            $start = new \DateTime($data['start']);
            $start->setTime(0, 0, 0);
            $end = new \DateTime($data['end']);
            $end->setTime(23, 59, 59);

            $orders = $this->orderRepository->findOrdersByDateRange($start, $end);

            $csv = CsvWriter::createFromString('');
            $csv->insertOne([
                'id',
                'number',
                'restaurant',
                'state',
                'shipped_at',
                'items_total',
                'total',
                'tax_total',
                'fee_total',
                'stripe_fee',
                'last_payment_state',
            ]);

            $records = [];
            foreach ($orders as $order) {

                $restaurant = $order->getRestaurant();

                // For now, we only export foodtech orders
                if (null === $restaurant) {
                    continue;
                }

                $lastPayment = null;
                if ($order->hasPayments()) {

                    $payments = $order->getPayments()->toArray();
                    usort($payments, function ($a, $b) {
                        return $a->getUpdatedAt() > $b->getUpdatedAt() ? -1 : 1;
                    });

                    $lastPayment = current($payments);
                }

                // Retrieve Stripe fees
                $stripeFee = null;
                if ($lastPayment->getState() === PaymentInterface::STATE_COMPLETED) {
                    $stripeFee = $this->getStripeFee($restaurant, $lastPayment, $start, $end);
                }

                $records[] = [
                    $order->getId(),
                    $order->getNumber(),
                    $restaurant->getName(),
                    $order->getState(),
                    $order->getShippedAt()->format('Y-m-d H:i'),
                    number_format($order->getItemsTotal() / 100, 2),
                    number_format($order->getTotal() / 100, 2),
                    number_format($order->getTaxTotal() / 100, 2),
                    number_format($order->getFeeTotal() / 100, 2),
                    $stripeFee ? number_format($stripeFee / 100, 2) : '',
                    $lastPayment ? $lastPayment->getState() : ''
                ];
            }
            $csv->insertAll($records);

            $event->getForm()->setData([
                'csv' => $csv->getContent()
            ]);
        });
    }
}
