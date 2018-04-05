<?php

namespace AppBundle\Command;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Menu\MenuItem;
use Stripe;
use Sylius\Component\Order\OrderTransitions;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateRandomOrderCommand extends ContainerAwareCommand
{
    private $doctrine;
    private $restaurantRepository;
    private $orderRepository;
    private $userManager;
    private $orderManager;
    private $stripe;

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:random')
            ->setDescription('Creates a random order. Useful for testing.')
            ->addArgument('restaurant', InputArgument::REQUIRED, 'The restaurant.')
            ->addArgument('username', InputArgument::REQUIRED, 'The username.')
            ->addArgument('shipped_at', InputArgument::OPTIONAL, 'The date of delivery.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->doctrine = $this->getContainer()->get('doctrine');
        $this->restaurantRepository = $this->doctrine->getRepository(Restaurant::class);
        $this->userManager = $this->getContainer()->get('fos_user.user_manager');

        $this->orderFactory = $this->getContainer()->get('sylius.factory.order');
        $this->orderRepository = $this->getContainer()->get('sylius.repository.order');
        $this->orderManager = $this->getContainer()->get('sylius.manager.order');

        $this->productVariantRepository = $this->getContainer()->get('sylius.repository.product_variant');
        $this->orderItemFactory = $this->getContainer()->get('sylius.factory.order_item');
        $this->orderItemQuantityModifier = $this->getContainer()->get('sylius.order_item_quantity_modifier');
        $this->orderModifier = $this->getContainer()->get('sylius.order_modifier');
        $this->stateMachineFactory = $this->getContainer()->get('sm.factory');

        Stripe\Stripe::setApiKey($this->getContainer()->get('coopcycle.settings_manager')->get('stripe_secret_key'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $restaurantId = $input->getArgument('restaurant');
        $username = $input->getArgument('username');
        $shippedAt = $input->getArgument('shipped_at');

        if ($shippedAt) {
            $shippedAt = new \DateTime($shippedAt);
        } else {
            $shippedAt = new \DateTime('+2 hours');
        }

        $restaurant = $this->restaurantRepository->find($restaurantId);
        $user = $this->userManager->findUserByUsername($username);

        $order = $this->createRandomOrder($restaurant);

        $order->setCustomer($user);
        $order->setRestaurant($restaurant);
        $order->setShippedAt($shippedAt);

        $this->orderRepository->add($order);

        $stripeToken = Stripe\Token::create([
            'card' => [
                'number'    => '4242424242424242',
                'exp_month' => date('m'),
                'exp_year'  => date('Y'),
                'cvc'       => '123'
            ]
        ]);

        $stripePayment = $order->getLastPayment();
        $stripePayment->setStripeToken($stripeToken->id);

        $stateMachine = $this->stateMachineFactory->get($order, OrderTransitions::GRAPH);
        $stateMachine->apply(OrderTransitions::TRANSITION_CREATE);

        $this->orderManager->flush();

        $output->writeln(sprintf('<info>Order #%d created!</info>', $order->getId()));
    }

    private function createRandomOrder(Restaurant $restaurant)
    {
        $menuItems = $restaurant->getMenu()->getAllItems();

        $menuItemsWithoutModifiers = $menuItems->filter(function (MenuItem $menuItem) {
            return count($menuItem->getModifiers()) === 0;
        })->getValues();

        $numberOfItems = random_int(1, count($menuItemsWithoutModifiers));

        $order = $this->orderFactory->createForRestaurant($restaurant);

        while (count($order->getItems()) < $numberOfItems) {

            $randomIndex = rand(0, (count($menuItemsWithoutModifiers) - 1));
            $menuItem = $menuItemsWithoutModifiers[$randomIndex];

            $productVariant = $this->productVariantRepository->findOneByMenuItem($menuItem);

            $cartItem = $this->orderItemFactory->createNew();
            $cartItem->setVariant($productVariant);
            $cartItem->setUnitPrice($productVariant->getPrice());

            $this->orderItemQuantityModifier->modify($cartItem, 1);
            $this->orderModifier->addToOrder($order, $cartItem);
        }

        return $order;
    }
}
