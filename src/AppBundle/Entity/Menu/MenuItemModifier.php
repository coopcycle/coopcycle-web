<?php


namespace AppBundle\Entity\Menu;


use ApiPlatform\Core\Annotation\ApiProperty;
use AppBundle\Entity\Base\Thing;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class MenuItemModifier
 * Represent a modifier that can be added to a MenuItem
 *
 * @ORM\Entity()
 *
 */
class MenuItemModifier extends Thing
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * The strategy to calculate the extra-price involved by adding the modifier.
     *
     * Typically: FREE no extra price, ADD_MENUITEM_PRICE add price of the extra, ADD_MODIFIER_PRICE add the fixed price of the menu item
     * @var string
     * @ORM\Column(type="string")
     *
     */
    protected $calculusStrategy;

    /**
     * @ORM\Column(type="float")
     * @ApiProperty(iri="https://schema.org/price")
     */
    protected $price;

    /**
     * The menu item this modifier belongs to
     *
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Menu\MenuItem", inversedBy="modifiers", cascade={"persist"})
     * @ORM\JoinColumn(name="menu_item_id", referencedColumnName="id")
     */
    protected $menuItem;

    /**
     * The choices the user can select from.
     *
     * @ORM\ManyToMany(targetEntity="AppBundle\Entity\Menu\MenuItem", cascade={"persist"})
     * @ORM\JoinTable(name="menu_item_modifier_menu_item",
     *                joinColumns={@ORM\JoinColumn(name="menu_item_modifier_id", referencedColumnName="id")},
     *                inverseJoinColumns={@ORM\JoinColumn(name="menu_item_id", referencedColumnName="id")})
     */
    protected $menuItemChoices;

    public function __construct()
    {
        $this->menuItemChoices = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getCalculusStrategy()
    {
        return $this->calculusStrategy;
    }

    /**
     * @param string $calculusStrategy
     */
    public function setCalculusStrategy($calculusStrategy)
    {
        $this->calculusStrategy = $calculusStrategy;
    }

    /**
     * @return float
     */
    public function getSelectedMenuItemPrice($selectedMenuItem)
    {
        if ($this->getCalculusStrategy() === 'FREE') {
            $price = (float)0;
        }
        else if ($this->getCalculusStrategy() === 'ADD_MENUITEM_PRICE') {
            $price = $selectedMenuItem->getPrice();
        }
        else if ($this->getCalculusStrategy() === 'ADD_MODIFIER_PRICE') {
            $price = $this->getPrice();
        }
        else {
            throw new \Exception("Unhandled modifier calculus method");
        }

        return $price;
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->price;
    }


    /**
     * @param float $price
     */
    public function setPrice($price)
    {
        $this->price = $price;
    }

    /**
     * @return mixed
     */
    public function getMenuItemChoices()
    {
        return $this->menuItemChoices;
    }

    /**
     * @param mixed $menuItemChoices
     */
    public function setMenuItemChoices($menuItemChoices)
    {
        $this->menuItemChoices = $menuItemChoices;
    }

    /**
     * @return mixed
     */
    public function getMenuItem()
    {
        return $this->menuItem;
    }

    /**
     * @param mixed $menuItem
     */
    public function setMenuItem($menuItem)
    {
        $this->menuItem = $menuItem;
    }



}