<?php

namespace AppBundle\Form;

use League\Csv\Writer as CsvWriter;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\Type as FormType;

class OrdersExportType extends AbstractType
{
    private $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
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
            $end = new \DateTime($data['end']);

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
