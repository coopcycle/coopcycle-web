<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Form\OrdersExportType;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\ReceiptGenerator;
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

    public function orderReceiptAction($orderNumber, Request $request)
    {
        $order = $this->get('sylius.repository.order')->findOneBy([
            'number'=> $orderNumber
        ]);

        $this->accessControl($order);

        if (!$order->hasReceipt()) {
            throw $this->createNotFoundException(sprintf('Receipt for order "%s" does not exist', $orderNumber));
        }

        $fileSystem = $this->get('receipts_filesystem');

        $filename = sprintf('%s.pdf', $orderNumber);

        if (!$fileSystem->has($filename)) {
            throw $this->createNotFoundException(sprintf('File %s.pdf does not exist', $orderNumber));
        }

        return new Response((string) $fileSystem->read($filename), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function generateOrderReceiptAction($orderNumber, Request $request, ReceiptGenerator $generator)
    {
        $order = $this->get('sylius.repository.order')->findOneBy([
            'number'=> $orderNumber
        ]);

        $this->accessControl($order);

        $receipt = $generator->create($order);
        $order->setReceipt($receipt);

        $this->getDoctrine()->getManager()->flush();

        $generator->generate($receipt, sprintf('%s.pdf', $order->getNumber()));

        return $this->redirect($request->headers->get('referer'));
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
