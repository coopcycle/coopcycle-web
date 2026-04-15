<?php

namespace AppBundle\Entity\Sylius;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use Sylius\Component\Promotion\Model\Promotion as BasePromotion;
use Sylius\Component\Promotion\Model\PromotionInterface;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted("ROLE_DISPATCHER")'),
    ],
    normalizationContext: ['groups' => ['promotion']]
)]
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
