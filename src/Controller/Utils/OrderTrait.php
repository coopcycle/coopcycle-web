<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Form\OrderExportType;
use AppBundle\Service\OrderManager;
use AppBundle\Sylius\Order\ReceiptGenerator;
use AppBundle\Utils\RestaurantStats;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use League\Flysystem\Filesystem;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\Translation\TranslatorInterface;

trait OrderTrait
{
    abstract protected function getOrderList(Request $request, $showCanceled = false);

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

    public function orderListAction(Request $request,
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager,
        RepositoryInterface $taxRateRepository,
        PaginatorInterface $paginator)
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

        [ $orders, $pages, $page ] = $this->getOrderList($request, $showCanceled);

        $parameters = [
            'orders' => $orders,
            'pages' => $pages,
            'page' => $page,
            'routes' => $request->attributes->get('routes'),
            'show_canceled' => $showCanceled,
        ];

        if ($this->isGranted('ROLE_ADMIN')) {

            $orderExportForm = $this->createForm(OrderExportType::class);
            $orderExportForm->handleRequest($request);

            if ($orderExportForm->isSubmitted() && $orderExportForm->isValid()) {

                $start = $orderExportForm->get('start')->getData();
                $end = $orderExportForm->get('end')->getData();

                $withMessenger = $orderExportForm->has('messenger') && $orderExportForm->get('messenger')->getData();

                $start->setTime(0, 0, 1);
                $end->setTime(23, 59, 59);

                $stats = new RestaurantStats(
                    $entityManager,
                    $start,
                    $end,
                    null,
                    $paginator,
                    $this->getParameter('kernel.default_locale'),
                    $translator,
                    $withVendorName = true,
                    $withMessenger
                );

                if (count($stats) === 0) {
                    $this->addFlash('error', $translator->trans('order.export.empty'));

                    return $this->redirectToRoute($request->attributes->get('_route'));
                }

                $filename = sprintf('coopcycle-orders-%s-%s.csv', $start->format('Y-m-d'), $end->format('Y-m-d'));

                $response = new Response($stats->toCsv());
                $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $filename
                ));

                return $response;
            }

            // https://cube.dev/docs/security
            $key = \Lcobucci\JWT\Signer\Key\InMemory::plainText($_SERVER['CUBEJS_API_SECRET']);
            $config = \Lcobucci\JWT\Configuration::forSymmetricSigner(
                new \Lcobucci\JWT\Signer\Hmac\Sha256(),
                $key
            );

            // https://github.com/lcobucci/jwt/issues/229
            $now = new \DateTimeImmutable('@' . time());

            $token = $config->builder()
                    ->expiresAt($now->modify('+1 hour'))
                    ->withClaim('database', $this->getParameter('database_name'))
                    ->getToken($config->signer(), $config->signingKey());

            $parameters['order_export_form'] = $orderExportForm->createView();
            $parameters['cube_token'] = $token->toString();
        }

        return $this->render($request->attributes->get('template'), $parameters, $response);
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

        if (!$receiptsFilesystem->has($filename)) {
            throw $this->createNotFoundException(sprintf('File %s.pdf does not exist', $orderNumber));
        }

        return new Response((string) $receiptsFilesystem->read($filename), 200, [
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
}
