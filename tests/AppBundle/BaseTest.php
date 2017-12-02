<?php

namespace AppBundle;

use AppBundle\Entity\Menu\MenuItem;
use AppBundle\Entity\Menu\MenuItemModifier;
use AppBundle\Entity\Menu\Modifier;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\DBAL\Logging\EchoSQLLogger;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
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

    protected function setUp()
    {
        parent::setUp();
        self::bootKernel();
        $this->doctrine = static::$kernel->getContainer()->get('doctrine');
        $this->em = $this->doctrine->getManager();
        // $this->em->getConnection()->getConfiguration()->setSQLLogger( new EchoSQLLogger());
    }

    protected function tearDown()
    {
        parent::tearDown();
        $purger = new ORMPurger($this->em);
        $purger->purge();
    }

    public function createMenuItem($name, $price, TaxCategoryInterface $taxCategory)
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

    public function createModifier($name, $price, TaxCategoryInterface $taxCategory)
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

    public function createMenuItemModifier($menuItem, $menuItemChoices, $calculusStrategy, $price)
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
}
