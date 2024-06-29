<?php

namespace AppBundle\Controller\Utils;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Exception\Pricing\NoRuleMatchedException;
use AppBundle\Form\DeliveryType;
use AppBundle\Service\DeliveryManager;
use AppBundle\Sylius\Customer\CustomerInterface;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

trait DeliveryTrait
{
    /**
     * @return array
     */
    abstract protected function getDeliveryRoutes();

    /**
     * @param OrderFactory $factory
     * @param Delivery $delivery
     * @param int $price
     * @param CustomerInterface $customer
     *
     * @return OrderInterface
     */
    protected function createOrderForDelivery(OrderFactory $factory, Delivery $delivery, int $price, ?CustomerInterface $customer = null, $attach = true)
    {
        return $factory->createForDelivery($delivery, $price, $customer, $attach);
    }

    protected function createDeliveryForm(Delivery $delivery, array $options = [])
    {
        return $this->createForm(DeliveryType::class, $delivery, $options);
    }

    protected function getDeliveryPrice(Delivery $delivery, PricingRuleSet $pricingRuleSet, DeliveryManager $deliveryManager)
    {
        $price = $deliveryManager->getPrice($delivery, $pricingRuleSet);

        if (null === $price) {
            throw new NoRuleMatchedException();
        }

        return (int) ($price);
    }

    public function deliveryAction($id,
        Request $request,
        OrderFactory $orderFactory,
        EntityManagerInterface $entityManager,
        OrderNumberAssignerInterface $orderNumberAssigner,
    )
    {
        $delivery = $entityManager
            ->getRepository(Delivery::class)
            ->find($id);

        $this->accessControl($delivery, 'view');

        $routes = $request->attributes->get('routes');

        $form = $this->createDeliveryForm($delivery, [
            'with_address_props' => true,
            'with_arbitrary_price' => null === $delivery->getOrder(),
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $delivery = $form->getData();

            $saveAsNew = $form->has('saveAsNew') && $form->get('saveAsNew')->isClicked();

            if ($saveAsNew) {
                $previousOrder = $delivery->getOrder();

                // Keep the original objects untouched, creating new ones instead
                $entityManager->detach($delivery);
                if (null !== $previousOrder) {
                    $entityManager->detach($previousOrder);
                }
                foreach ($delivery->getTasks() as $task) {
                    $entityManager->detach($task);
                }

                $store = $delivery->getStore();

                $newTasks = array_map(function($task){
                    return $task->duplicate();
                }, $delivery->getTasks());

                $newDelivery = Delivery::createWithTasks(...$newTasks);
                $newDelivery->setStore($store);

                if (null !== $previousOrder) {
                    $newOrder = $this->createOrderForDelivery($orderFactory, $newDelivery, $previousOrder->getItemsTotal(), $previousOrder->getCustomer());
                    $entityManager->persist($newOrder);

                    // must be done before assigning a number
                    $entityManager->flush();

                    $orderNumberAssigner->assignNumber($newOrder);
                    $newOrder->setState(OrderInterface::STATE_ACCEPTED);

                    $entityManager->flush();

                } else {
                    $entityManager->persist($newDelivery);
                    $entityManager->flush();
                }

            } else {
                $useArbitraryPrice = $this->isGranted('ROLE_ADMIN') &&
                    $form->has('arbitraryPrice') && true === $form->get('arbitraryPrice')->getData();

                if ($useArbitraryPrice) {
                    $this->createOrderForDeliveryWithArbitraryPrice($form, $orderFactory, $delivery,
                        $entityManager, $orderNumberAssigner);
                } else {
                    $entityManager->persist($delivery);
                    $entityManager->flush();
                }
            }

            return $this->redirectToRoute($routes['success']);
        }

        return $this->render('delivery/form.html.twig', [
            'layout' => $request->attributes->get('layout'),
            'delivery' => $delivery,
            'form' => $form->createView(),
            'debug_pricing' => $request->query->getBoolean('debug', false),
            'back_route' => $routes['back'],
        ]);
    }

    protected function createOrderForDeliveryWithArbitraryPrice(
        FormInterface $form,
        OrderFactory $orderFactory,
        Delivery $delivery,
        EntityManagerInterface $entityManager,
        OrderNumberAssignerInterface $orderNumberAssigner
    )
    {
        $variantPrice = $form->get('variantPrice')->getData();
        $variantName = $form->get('variantName')->getData();

        $order = $this->createOrderForDelivery($orderFactory, $delivery, $variantPrice);

        /** @var OrderItemInterface */
        $orderItem = $order->getItems()->first();
        $orderItem->setImmutable(true);

        $variant = $orderItem->getVariant();

        $variant->setName($variantName);
        $variant->setCode(Uuid::uuid4()->toString());

        $order->setState(OrderInterface::STATE_ACCEPTED);

        $entityManager->persist($order);
        $entityManager->flush();

        $orderNumberAssigner->assignNumber($order);

        $entityManager->flush();
    }
}
