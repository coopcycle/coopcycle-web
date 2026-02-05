<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\ReceiptGenerator;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait OrderTrait
{
    private function orderAsJson(Order $order)
    {
        $orderNormalized = $this->normalizer->normalize($order, 'jsonld', [
            'groups' => ['order', 'address']
        ]);

        return new JsonResponse($orderNormalized, 200);
    }

    public function orderReceiptPreviewAction($id, Request $request, ReceiptGenerator $generator, OrderRepository $orderRepository)
    {
        $order = $orderRepository->find($id);

        $this->denyAccessUnlessGranted('view', $order);

        $output = $generator->render($order);

        return new Response($output, 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function orderReceiptAction($orderNumber, Request $request, Filesystem $receiptsFilesystem, OrderRepository $orderRepository)
    {
        $order = $orderRepository->findOneBy([
            'number'=> $orderNumber
        ]);

        $this->denyAccessUnlessGranted('view', $order);

        if (!$order->hasReceipt()) {
            throw $this->createNotFoundException(sprintf('Receipt for order "%s" does not exist', $orderNumber));
        }

        $filename = sprintf('%s.pdf', $orderNumber);

        if (!$receiptsFilesystem->fileExists($filename)) {
            throw $this->createNotFoundException(sprintf('File %s.pdf does not exist', $orderNumber));
        }

        return new Response($receiptsFilesystem->read($filename), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function generateOrderReceiptAction($orderNumber, Request $request,
        ReceiptGenerator $generator,
        EntityManagerInterface $entityManager,
        OrderRepository $orderRepository)
    {
        $billingAddress = $request->request->get('billingAddress');

        $order = $orderRepository->findOneBy([
            'number'=> $orderNumber
        ]);

        $this->denyAccessUnlessGranted('view', $order);

        $receipt = $generator->create($order);

        if (!empty($billingAddress)) {
            $receipt->setBillingAddress($billingAddress);
        }

        $order->setReceipt($receipt);

        $entityManager->flush();

        $generator->generate($order, sprintf('%s.pdf', $order->getNumber()));

        return $this->redirect($request->headers->get('referer'));
    }

    public function acceptOrderAction($id, Request $request, OrderManager $orderManager, EntityManagerInterface $entityManager)
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        $this->denyAccessUnlessGranted('accept', $order);

        try {
            $orderManager->accept($order);
            $entityManager->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function refuseOrderAction($id, Request $request, OrderManager $orderManager, EntityManagerInterface $entityManager)
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        $this->denyAccessUnlessGranted('refuse', $order);

        $reason = $request->request->get('reason', null);

        try {
            $orderManager->refuse($order, $reason);
            $entityManager->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function delayOrderAction($id, Request $request, OrderManager $orderManager, EntityManagerInterface $entityManager)
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        $this->denyAccessUnlessGranted('delay', $order);

        try {
            $orderManager->delay($order);
            $entityManager->flush();
        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    private function cancelOrderById($id, OrderManager $orderManager, EntityManagerInterface $entityManager, $reason = null)
    {
        $order = $entityManager->getRepository(Order::class)->find($id);
        $this->denyAccessUnlessGranted('cancel', $order);

        $orderManager->cancel($order, $reason);
        $entityManager->flush();

        return $order;
    }

    public function cancelOrderAction($id, Request $request, OrderManager $orderManager, EntityManagerInterface $entityManager)
    {
        $reason = $request->request->get('reason', null);

        $order = $this->cancelOrderById($id, $orderManager, $entityManager, $reason);

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function fulfillOrderAction($id, Request $request, OrderManager $orderManager, EntityManagerInterface $entityManager)
    {
        $order = $entityManager->getRepository(Order::class)->find($id);
        $this->denyAccessUnlessGranted('fulfill', $order);

        try {

            $orderManager->fulfill($order);
            $entityManager->flush();

        } catch (\Exception $e) {
            // TODO Add flash message
        }

        if ($request->isXmlHttpRequest()) {

            return $this->orderAsJson($order);
        }
    }

    public function editOrderAction($id, EntityManagerInterface $entityManager)
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        $delivery = $order->getDelivery();

        if (null === $delivery) {
            throw $this->createNotFoundException(sprintf('Order #%d does not have a delivery', $id));
        }

        $this->denyAccessUnlessGranted('view', $delivery);

        return $this->redirectToRoute('admin_delivery', ['id' => $delivery->getId()]);
    }
}
