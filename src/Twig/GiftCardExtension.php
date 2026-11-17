<?php

namespace AppBundle\Twig;

use AppBundle\Service\GiftCard\GiftCardStatus;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the *runtime* gift card switch to templates.
 *
 * The `gift_cards_enabled` Twig global is the deployment-time env var, which
 * only says the feature exists on this instance. Customer-facing links have
 * to ask this instead, or an admin who hasn't finished setting the
 * programme up would find the purchase page already advertised.
 */
class GiftCardExtension extends AbstractExtension
{
    public function __construct(private readonly GiftCardStatus $giftCardStatus)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('gift_cards_active', [$this->giftCardStatus, 'isActive']),
        ];
    }
}
