<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Invoice;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Form\OrdersExportType;
use AppBundle\Service\OrderManager;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait OrderTrait
{
    abstract protected function getOrderList(Request $request);

    private function orderAsJson(Order $order)
    {
        $orderNormalized = $this->get('serializer')->normalize($order, 'jsonld', [
            'resource_class' => Order::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['order', 'place']
        ]);

        return new JsonResponse($orderNormalized, 200);
    }

    public function orderListAction(Request $request)
    {
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

        return $this->render($request->attributes->get('template'), [
            'orders' => $orders,
            'pages' => $pages,
            'page' => $page,
            'routes' => $request->attributes->get('routes'),
            'show_canceled' => $showCanceled,
        ], $response);
    }

    public function orderInvoiceAction($orderNumber, $invoiceNumber, Request $request)
    {
        $order = $this->get('sylius.repository.order')->findOneBy([
            'number'=> $orderNumber
        ]);

        $this->accessControl($order);

        $invoice = $this->getDoctrine()->getRepository(Invoice::class)->findOneBy([
            'number'=> $invoiceNumber
        ]);

        if (!$invoice) {
            throw $this->createNotFoundException(sprintf('Invoice %s does not exist', $invoiceNumber));
        }

        if (!$order->hasInvoice($invoice)) {
            throw $this->createAccessDeniedException(sprintf('Invoice %s does not belong to order %s', $invoiceNumber, $orderNumber));
        }

        $fileSystem = $this->get('invoices_filesystem');

        $filename = sprintf('%s.pdf', $invoiceNumber);

        if (!$fileSystem->has($filename)) {
            throw $this->createNotFoundException(sprintf('File %s.pdf does not exist', $invoiceNumber));
        }

        return new Response((string) $fileSystem->read($filename), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function acceptOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);

        $this->accessControl($order->getRestaurant());

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

        $this->accessControl($order->getRestaurant());

        try {
            $orderManager->refuse($order);
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

        $this->accessControl($order->getRestaurant());

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

    private function cancelOrderById($id, OrderManager $orderManager)
    {
        $order = $this->get('sylius.repository.order')->find($id);
        $this->accessControl($order->getRestaurant());

        $orderManager->cancel($order);
        $this->get('sylius.manager.order')->flush();

        return $order;
    }

    public function cancelOrderAction($id, Request $request, OrderManager $orderManager)
    {
        $order = $this->cancelOrderById($id, $orderManager);

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }
}
