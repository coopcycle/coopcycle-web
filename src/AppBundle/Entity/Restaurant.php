<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use AppBundle\Action\MyRestaurants;
use AppBundle\Action\Restaurant\Close as CloseRestaurant;
use AppBundle\Action\Restaurant\Menu;
use AppBundle\Action\Restaurant\Menus;
use AppBundle\Annotation\Enabled;
use AppBundle\Api\Controller\Restaurant\ChangeState;
use AppBundle\Api\Dto\RestaurantInput;
// use AppBundle\Entity\Base\FoodEstablishment;
use AppBundle\Entity\Base\LocalBusiness as BaseLocalBusiness;
use AppBundle\Validator\Constraints as CustomAssert;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\Timestampable;
use AppBundle\LoopEat\OAuthCredentialsTrait as LoopEatOAuthCredentialsTrait;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * A restaurant.
 *
 * @see http://schema.org/Restaurant Documentation on Schema.org
 *
 * @ApiResource(iri="http://schema.org/Restaurant",
 *   attributes={
 *     "denormalization_context"={"groups"={"order_create", "restaurant_update"}},
 *     "normalization_context"={"groups"={"restaurant", "address", "order"}}
 *   },
 *   collectionOperations={
 *     "get"={"method"="GET"},
 *     "me_restaurants"={
 *       "method"="GET",
 *       "path"="/me/restaurants",
 *       "controller"=MyRestaurants::class
 *     }
 *   },
 *   itemOperations={
 *     "get"={"method"="GET"},
 *     "restaurant_menu"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/menu",
 *       "controller"=Menu::class,
 *       "normalization_context"={"groups"={"restaurant_menu"}},
 *     },
 *     "restaurant_menus"={
 *       "method"="GET",
 *       "path"="/restaurants/{id}/menus",
 *       "controller"=Menus::class,
 *       "normalization_context"={"groups"={"restaurant_menus"}},
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "input"=RestaurantInput::class,
 *       "denormalization_context"={"groups"={"restaurant_update"}},
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object))"
 *     },
 *     "close"={
 *       "method"="PUT",
 *       "path"="/restaurants/{id}/close",
 *       "controller"=CloseRestaurant::class,
 *       "access_control"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object))",
 *     }
 *   },
 *   subresourceOperations={
 *     "orders_get_subresource"={
 *       "security"="is_granted('ROLE_ADMIN') or (is_granted('ROLE_RESTAURANT') and user.ownsRestaurant(object))"
 *     },
 *   },
 * )
 */
class Restaurant extends LocalBusiness
{
    /**
     * @ApiSubresource
     */
    protected $orders;

    /**
     * @ApiSubresource
     */
    protected $products;

    public function __construct()
    {
        parent::__construct();

        $this->type = 'restaurant';
        $this->orders = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function getOrders()
    {
        return $this->orders;
    }

    public function getProducts()
    {
        return $this->products;
    }

    public function hasProduct(ProductInterface $product)
    {
        return $this->products->contains($product);
    }

    public function addProduct(ProductInterface $product)
    {
        $product->setRestaurant($this);

        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }
    }
}
