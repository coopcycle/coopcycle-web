<?php

namespace AppBundle\Entity\Sylius;

use Sylius\Component\Promotion\Model\Promotion as BasePromotion;
use Sylius\Component\Promotion\Model\PromotionInterface;

class Promotion extends BasePromotion implements PromotionInterface
{
    /** @var bool */
    protected $featured = false;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function setFeatured($featured = true): void
    {
        $this->featured = $featured;
    }

    /**
     * {@inheritdoc}
     */
    public function isFeatured(): bool
    {
        return $this->featured;
    }
}

