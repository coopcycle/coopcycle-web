<?php

namespace AppBundle\Twig\Components;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\Product;
use AppBundle\Service\RecommenderProductService;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ProductRecommendations
{
    use DefaultActionTrait;

    #[LiveProp]
    public Order $order;

    public function __construct(private readonly RecommenderProductService $recommender)
    {}

    /** @return Product[] */
    public function getProducts(): array
    {
        return $this->recommender->getProductsForOrder($this->order);
    }
}
