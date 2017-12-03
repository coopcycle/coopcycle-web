<?php

namespace AppBundle;

use AppBundle\Entity\Address;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Contract;
use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Menu\MenuItemModifier;
use AppBundle\Entity\Menu\Modifier;
use AppBundle\Entity\Restaurant;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Logging\EchoSQLLogger;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;

/**
 * Class BaseTest
 * @package Tests\AppBundle
 *
 * Provides a base implementation for tests with Doctrine
 */
abstract class BaseTest extends KernelTestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var Registry
     */
    protected $doctrine;

    private $userManipulator;

    protected function setUp()
    {
        parent::setUp();
        self::bootKernel();
        $this->doctrine = static::$kernel->getContainer()->get('doctrine');
        $this->em = $this->doctrine->getManager();
        $this->userManipulator = static::$kernel->getContainer()->get('fos_user.util.user_manipulator');
        // $this->em->getConnection()->getConfiguration()->setSQLLogger( new EchoSQLLogger());
    }

    protected function tearDown()
    {
        parent::tearDown();
        $purger = new ORMPurger($this->em);
        $purger->purge();
    }

    protected function createTaxCategory($categoryName, $categoryCode, $rateName, $rateCode, $rateAmount, $rateCalculator = 'default')
    {
        $taxCategoryFactory = static::$kernel->getContainer()->get('sylius.factory.tax_category');
        $taxCategoryManager = static::$kernel->getContainer()->get('sylius.manager.tax_category');

        $taxRateFactory = static::$kernel->getContainer()->get('sylius.factory.tax_rate');
        $taxRateManager = static::$kernel->getContainer()->get('sylius.manager.tax_rate');

        $taxCategory = $taxCategoryFactory->createNew();
        $taxCategory->setName($categoryName);
        $taxCategory->setCode($categoryCode);

        $taxCategoryManager->persist($taxCategory);
        $taxCategoryManager->flush();

        $taxRate = $taxRateFactory->createNew();
        $taxRate->setName($rateName);
        $taxRate->setCode($rateCode);
        $taxRate->setCategory($taxCategory);
        $taxRate->setAmount($rateAmount);
        $taxRate->setIncludedInPrice(true);
        $taxRate->setCalculator($rateCalculator);

        $taxRateManager->persist($taxRate);
        $taxRateManager->flush();

        return $taxCategory;
    }

    protected function createMenuItem($name, $price, TaxCategoryInterface $taxCategory)
    {
        $item = new MenuItem();
        $item->setName($name);
        $item->setPrice($price);
        $item->setTaxCategory($taxCategory);

        $menuItemManager = $this->doctrine->getManagerForClass(MenuItem::class);
        $menuItemManager->persist($item);
        $menuItemManager->flush();

        return $item;
    }

    protected function createModifier($name, $price, TaxCategoryInterface $taxCategory)
    {
        $item = new Modifier();
        $item->setName($name);
        $item->setPrice($price);
        $item->setTaxCategory($taxCategory);

        $manager = $this->doctrine->getManagerForClass(Modifier::class);
        $manager->persist($item);
        $manager->flush();

        return $item;
    }

    protected function createMenuItemModifier($menuItem, $menuItemChoices, $calculusStrategy, $price)
    {
        $modifier = new MenuItemModifier();
        $modifier->setMenuItem($menuItem);
        $modifier->setModifierChoices($menuItemChoices);
        $modifier->setCalculusStrategy($calculusStrategy);
        $modifier->setPrice($price);

        $manager = $this->doctrine->getManagerForClass(MenuItemModifier::class);
        $manager->persist($modifier);
        $manager->flush();

        $menuItem->getModifiers()->add($modifier);

        return $modifier;
    }

    protected function createRestaurant(Address $address, array $openingHours, $minimumCartAmount, $flatDeliveryPrice)
    {
        $contract = new Contract();
        $contract->setMinimumCartAmount($minimumCartAmount);
        $contract->setFlatDeliveryPrice($flatDeliveryPrice);

        $restaurant = new Restaurant();
        $restaurant->setOpeningHours($openingHours);
        $restaurant->setAddress($address);
        $restaurant->setContract($contract);

        $this->doctrine->getManagerForClass(Restaurant::class)->persist($restaurant);
        $this->doctrine->getManagerForClass(Restaurant::class)->flush();

        return $restaurant;
    }

    protected function createUser($username)
    {
        $user = $this->userManipulator->create($username, 'password', "test@coopcycle.org", true, false);
        $this->userManipulator->addRole($username, 'ROLE_USER');

        return $user;
    }

    protected function authenticate(UserInterface $user)
    {
        $token = new UsernamePasswordToken($user, null, 'secured_area', array('ROLE_USER'));

        $tokenStorage = static::$kernel->getContainer()->get('security.token_storage');
        $tokenStorage->setToken($token);
    }
}
