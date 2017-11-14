<?php

namespace AppBundle\Command;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Order;
use AppBundle\Entity\OrderItem;
use AppBundle\Entity\Menu\MenuItem;
use Stripe;
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
            ->addArgument('delivery_date', InputArgument::REQUIRED, 'The date of delivery.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->doctrine = $this->getContainer()->get('doctrine');
        $this->restaurantRepository = $this->doctrine->getRepository(Restaurant::class);
        $this->orderRepository = $this->doctrine->getRepository(Order::class);
        $this->userManager = $this->getContainer()->get('fos_user.user_manager');
        $this->stripe = Stripe\Stripe::setApiKey($this->getContainer()->getParameter('stripe_secret_key'));
        $this->orderManager = $this->getContainer()->get('order.manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $restaurantId = $input->getArgument('restaurant');
        $username = $input->getArgument('username');
        $deliveryDate = $input->getArgument('delivery_date');

        $restaurant = $this->restaurantRepository->find($restaurantId);
        $user = $this->userManager->findUserByUsername($username);

        $order = $this->createRandomOrder($restaurant);

        $order->setCustomer($user);
        $order->setRestaurant($restaurant);

        $delivery = new Delivery($order);
        $delivery->setDate(new \DateTime($deliveryDate));
        $delivery->setOriginAddress($restaurant->getAddress());
        $delivery->setDeliveryAddress($user->getAddresses()->first());

        $stripeToken = Stripe\Token::create([
            'card' => [
                'number'    => '4242424242424242',
                'exp_month' => date('m'),
                'exp_year'  => date('Y'),
                'cvc'       => '123'
            ]
        ]);

        $this->doctrine->getManagerForClass(Order::class)->persist($order);
        $this->doctrine->getManagerForClass(Order::class)->flush();

        try {

            $this->orderManager->pay($order, $stripeToken->id);
            $this->doctrine->getManagerForClass(Order::class)->flush();

            $output->writeln(sprintf('Order #%d created!', $order->getId()));

        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
        }
    }

    private function createRandomOrder(Restaurant $restaurant)
    {
        $menuItems = $restaurant->getMenu()->getAllItems();

        $menuItemsWithoutModifiers = $menuItems->filter(function (MenuItem $menuItem) {
            return count($menuItem->getModifiers()) === 0;
        })->getValues();

        $numberOfItems = random_int(1, count($menuItemsWithoutModifiers));

        $order = new Order();

        while (count($order->getOrderedItem()) < $numberOfItems) {

            $randomIndex = rand(0, (count($menuItemsWithoutModifiers) - 1));
            $menuItem = $menuItemsWithoutModifiers[$randomIndex];

            $orderItem = new OrderItem();
            $orderItem->setMenuItem($menuItem);
            $orderItem->setQuantity(1);

            $order->addOrderedItem($orderItem);
        }

        return $order;
    }
}
