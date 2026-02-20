<?php

namespace AppBundle\Entity\LocalBusiness;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Serializer\Annotation\Groups;

class CollectionItem
{
    private $id;
    private $collection;
    private $position;

    /**
     * @var LocalBusiness
     */
    #[Groups(['shop_collection'])]
    private $shop;

    public function getShop()
    {
        return $this->shop;
    }

    public function setCollection(Collection $collection)
    {
        $this->collection = $collection;
    }

    public function getCollection()
    {
        return $this->collection;
    }

    public function setShop(LocalBusiness $shop)
    {
        $this->shop = $shop;
    }

    public function setPosition(int $position)
    {
        $this->position = $position;
    }

    public function getPosition()
    {
        return $this->position;
    }
}

