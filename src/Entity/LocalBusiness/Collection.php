<?php

namespace AppBundle\Entity\LocalBusiness;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Api\Dto\ShopCollectionInput;
use AppBundle\Api\State\ShopCollectionProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Timestampable\Traits\Timestampable;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'ShopCollection',
    operations: [
        new Get(
            uriTemplate: '/shop_collections/{id}',
        ),
        new GetCollection(
            uriTemplate: '/shop_collections',
        ),
        new Post(
            uriTemplate: '/shop_collections',
            processor: ShopCollectionProcessor::class,
            input: ShopCollectionInput::class,
            security: 'is_granted("ROLE_ADMIN")'
        ),
        new Put(
            uriTemplate: '/shop_collections/{id}',
            processor: ShopCollectionProcessor::class,
            input: ShopCollectionInput::class,
            security: 'is_granted("ROLE_ADMIN")'
        ),
        new Delete(
            uriTemplate: '/shop_collections/{id}',
            security: 'is_granted("ROLE_ADMIN")'
        ),
    ],
    normalizationContext: ['groups' => ['shop_collection']],
)]
class Collection
{
    use Timestampable;

    private $id;

    /**
     * @var ArrayCollection<CollectionItem> $items
     */
    private $items;

    #[Groups(['shop_collection'])]
    private string $title;

    private string $subtitle;

    #[Groups(['shop_collection'])]
    private string $slug;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setSubtitle(string $subtitle)
    {
        $this->subtitle = $subtitle;
    }

    public function getSubtitle()
    {
        return $this->subtitle;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function addShop(LocalBusiness $shop)
    {
        $item = new CollectionItem();
        $item->setCollection($this);
        $item->setShop($shop);
        $item->setPosition(0);

        $this->items->add($item);
    }

    #[Groups(['shop_collection'])]
    public function getShops()
    {
        return $this->items->map(fn (CollectionItem $i) => $i->getShop());
    }
}
