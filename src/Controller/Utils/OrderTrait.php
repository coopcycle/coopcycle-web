<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Form\OrderExportType;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\ReceiptGenerator;
use AppBundle\Utils\RestaurantStats;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Translation\TranslatorInterface;

trait OrderTrait
{
    abstract protected function getOrderList(Request $request);

    private function orderAsJson(Order $order)
    {
        $orderNormalized = $this->get('serializer')->normalize($order, 'jsonld', [
            'resource_class' => Order::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['order', 'address']
        ]);

        return new JsonResponse($orderNormalized, 200);
    }

    public function orderListAction(Request $request, TranslatorInterface $translator, EntityManagerInterface $em)
    {
        $filter = $em->getFilters()->disable('enabled_filter');

        $response = new Response();

        $showCanceled = false;
        if ($request->query->has('show_canceled')) {
            $showCanceled = $request->query->getBoolean('show_canceled');
            $response->headers->setCookie(new Cookie('__show_canceled', $showCanceled ? 'on' : 'off'));
        } elseif ($request->cookies->has('__show_canceled')) {
            $showCanceled = $request->cookies->getBoolean('__show_canceled');
        }

        $routes = $request->attributes->get('routes');

        [ $orders, $pages, $page ] = $this->getOrderList($request);

        $orderExportForm = $this->createForm(OrderExportType::class);
        $orderExportForm->handleRequest($request);

        if ($orderExportForm->isSubmitted() && $orderExportForm->isValid()) {

            $date = $orderExportForm->get('date')->getData();

            $withMessenger = $orderExportForm->has('messenger') && $orderExportForm->get('messenger')->getData();

            $start = clone $date;
            $end = clone $date;

            $start->setTime(0, 0, 1);
            $end->setTime(23, 59, 59);

            $ordersToExport = $this->getDoctrine()->getRepository(Order::class)
                ->findFulfilledOrdersByDateRange(
                    $start,
                    $end
                );

            // TODO Manage empty list

            $stats = new RestaurantStats(
                $this->getParameter('kernel.default_locale'),
                $ordersToExport,
                $this->get('sylius.repository.tax_rate'),
                $translator,
                true,
                $withMessenger
            );

            $filename = sprintf('coopcycle-orders-%s.csv', $date->format('Y-m-d'));

            $response = new Response($stats->toCsv());
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            ));

            return $response;
        }

        return $this->render($request->attributes->get('template'), [
            'orders' => $orders,
            'pages' => $pages,
            'page' => $page,
            'routes' => $request->attributes->get('routes'),
            'show_canceled' => $showCanceled,
            'order_export_form' => $orderExportForm->createView(),
        ], $response);
    }

    public function orderReceiptPreviewAction($id, Request $request, ReceiptGenerator $generator)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->denyAccessUnlessGranted('view', $order);

        $receipt = $generator->create($order);
        $order->setReceipt($receipt);

        $output = $generator->render($receipt);

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function orderReceiptAction($orderNumber, Request $request, Filesystem $receiptsFilesystem)
    {
        $order = $this->get('sylius.repository.order')->findOneBy([
            'number'=> $orderNumber
        ]);

        $this->denyAccessUnlessGranted('view', $order);

        if (!$order->hasReceipt()) {
            throw $this->createNotFoundException(sprintf('Receipt for order "%s" does not exist', $orderNumber));
        }

        $filename = sprintf('%s.pdf', $orderNumber);

        if (!$receiptsFilesystem->has($filename)) {
            throw $this->createNotFoundException(sprintf('File %s.pdf does not exist', $orderNumber));
        }

        return new Response((string) $receiptsFilesystem->read($filename), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function generateOrderReceiptAction($orderNumber, Request $request, ReceiptGenerator $generator)
    {
        $billingAddress = $request->request->get('billingAddress');

        $order = $this->get('sylius.repository.order')->findOneBy([
            'number'=> $orderNumber
        ]);

        $this->denyAccessUnlessGranted('view', $order);

        $receipt = $generator->create($order);

        if (!empty($billingAddress)) {
            $receipt->setBillingAddress($billingAddress);
        }

        $order->setReceipt($receipt);

        $this->getDoctrine()->getManager()->flush();

        $generator->generate($receipt, sprintf('%s.pdf', $order->getNumber()));

        return $this->redirect($request->headers->get('referer'));
    }

    public function acceptOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->denyAccessUnlessGranted('accept', $order);

        try {
            $orderManager->accept($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function refuseOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->denyAccessUnlessGranted('refuse', $order);

        $reason = $request->request->get('reason', null);

        try {
            $orderManager->refuse($order, $reason);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function delayOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->denyAccessUnlessGranted('delay', $order);

        try {
            $orderManager->delay($order);
            $this->get('sylius.manager.order')->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    private function cancelOrderById($id, OrderManager $orderManager, $reason = null)
    {
        $order = $this->get('sylius.repository.order')->find($id);
        $this->denyAccessUnlessGranted('cancel', $order);

        $orderManager->cancel($order, $reason);
        $this->get('sylius.manager.order')->flush();

        return $order;
    }

    public function cancelOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $reason = $request->request->get('reason', null);

        $order = $this->cancelOrderById($id, $orderManager, $reason);

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function fulfillOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);
        $this->denyAccessUnlessGranted('fulfill', $order);

        try {

            $orderManager->fulfill($order);
            $this->get('sylius.manager.order')->flush();

        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }
}
