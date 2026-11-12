<?php

namespace AppBundle\Service\Precog;

use AppBundle\Service\LiveUpdates;
use AppBundle\Sylius\Order\OrderInterface;
use Redis;

/**
 * Keeps track of carts that are on their way to becoming orders, so the
 * dispatch board can show where work is about to land.
 *
 * Nothing here is authoritative and nothing here is actionable. A ghost is a
 * forecast: it expires on its own, it carries no identity, and it disappears the
 * moment the real order shows up. The board is expected to treat it as weather,
 * not as a work item.
 */
class PrecogTracker
{
    /**
     * How long a ghost outlives the last heartbeat that refreshed it.
     *
     * Long enough to ride out a slow page or a tab switch, short enough that an
     * abandoned cart is off the map before a dispatcher could act on it. The
     * browser pings at a third of this, so two missed heartbeats still keep it.
     */
    public const TTL = 90;

    public const PAGE_CART     = 'cart';
    public const PAGE_CHECKOUT = 'checkout';
    public const PAGE_PAYMENT  = 'payment';

    private const INDEX_KEY = 'precog:index';
    private const ITEM_KEY  = 'precog:item:%s';

    public function __construct(
        private Redis $redis,
        private LiveUpdates $liveUpdates,
        private string $secret,
        private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Records a heartbeat from a checkout page.
     *
     * The page says which page it is; this decides which rung that puts the cart
     * on. A browser that claims to be on the payment page with an empty cart
     * simply does not get a ghost.
     *
     * @return PrecogStage|null The rung the cart ended up on, or null if it does
     *                          not qualify for one at all.
     */
    public function ping(?OrderInterface $cart, string $page, bool $submitted = false): ?PrecogStage
    {
        if (!$this->enabled || null === $cart) {
            return null;
        }

        $stage = $this->stageFor($cart, $page, $submitted);

        if (null === $stage) {
            return null;
        }

        $position = $this->coarsen($cart, $stage);

        if (null === $position) {
            return null;
        }

        $id = $this->idFor($cart->getId());
        $key = sprintf(self::ITEM_KEY, $id);

        $previous = $this->redis->get($key);
        $previousStage = is_string($previous)
            ? (json_decode($previous, true)['stage'] ?? null)
            : null;

        $ghost = [
            'id'        => $id,
            'stage'     => $stage->value,
            'rank'      => $stage->rank(),
            'precision' => $stage->precisionInMeters(),
            'latitude'  => $position[0],
            'longitude' => $position[1],
            'vendor'    => $cart->getVendor()?->getName(),
            'slot'      => $this->slotOf($cart),
            'expiresAt' => (new \DateTime(sprintf('+%d seconds', self::TTL)))->format(\DateTime::ATOM),
        ];

        $this->redis->setex($key, self::TTL, json_encode($ghost));
        $this->redis->zAdd(self::INDEX_KEY, time() + self::TTL, $id);

        // A heartbeat that changes nothing refreshes the TTL and stays quiet.
        // Publishing every 20 seconds per open cart would put more traffic on the
        // dispatch channel than the actual deliveries do.
        if ($previousStage !== $stage->value) {
            $this->liveUpdates->toDispatchers('precog:updated', ['precog' => $ghost]);
        }

        return $stage;
    }

    /**
     * Drops the ghost for a cart that has become an order.
     *
     * Published unconditionally, even when no ghost was stored: a board that
     * joined late may be holding one this process never wrote, and a `precog:gone`
     * for an id nobody has is free.
     */
    public function forget(int $cartId): void
    {
        if (!$this->enabled) {
            return;
        }

        $id = $this->idFor($cartId);

        $this->redis->del(sprintf(self::ITEM_KEY, $id));
        $this->redis->zRem(self::INDEX_KEY, $id);

        $this->liveUpdates->toDispatchers('precog:gone', ['precog' => ['id' => $id]]);
    }

    /**
     * Every ghost currently in flight, for a board that has just loaded.
     *
     * Redis expires the items on its own but cannot expire them out of the index,
     * so the index is pruned here, on read, which is the only place it matters.
     */
    public function all(): array
    {
        if (!$this->enabled) {
            return [];
        }

        $this->redis->zRemRangeByScore(self::INDEX_KEY, '-inf', (string) time());

        $ids = $this->redis->zRange(self::INDEX_KEY, 0, -1);

        if (empty($ids)) {
            return [];
        }

        $keys = array_map(fn ($id) => sprintf(self::ITEM_KEY, $id), $ids);

        $ghosts = [];

        foreach ($this->redis->mGet($keys) as $raw) {
            if (!is_string($raw)) {
                continue;
            }

            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $ghosts[] = $decoded;
            }
        }

        return $ghosts;
    }

    /**
     * An opaque, stable handle for a cart.
     *
     * The board needs to tell one ghost from another and to match a `precog:gone`
     * to the halo it removes, but it has no business knowing which cart that is.
     * Keyed on the app secret so the mapping cannot be reversed by guessing ids.
     */
    public function idFor(int $cartId): string
    {
        return substr(hash_hmac('sha256', (string) $cartId, $this->secret), 0, 16);
    }

    private function stageFor(OrderInterface $cart, string $page, bool $submitted): ?PrecogStage
    {
        if (null === $cart->getId() || !$cart->hasVendor()) {
            return null;
        }

        return match ($page) {
            self::PAGE_CART     => PrecogStage::CartAddress,
            self::PAGE_CHECKOUT => $this->isReadyToPay($cart)
                ? PrecogStage::CheckoutReady
                : PrecogStage::CheckoutOpen,
            self::PAGE_PAYMENT  => $submitted
                ? PrecogStage::PaymentSubmitted
                : PrecogStage::PaymentOpen,
            default => null,
        };
    }

    /**
     * Whether the cart could be paid for as it stands.
     *
     * Deliberately a cheap structural check and not the full validator: this runs
     * on every heartbeat of every open cart, and the three things it looks at are
     * what separates "still filling in the form" from "about to pay" anyway.
     */
    private function isReadyToPay(OrderInterface $cart): bool
    {
        return null !== $cart->getShippingAddress()
            && null !== $cart->getShippingTimeRange()
            && null !== $cart->getCustomer();
    }

    /**
     * Rounds the delivery address onto a grid whose cell is about as wide as the
     * precision the stage allows.
     *
     * Rounding rather than jittering, so the point is stable between heartbeats:
     * a dot that wandered every twenty seconds would read as movement, and the
     * one thing a ghost must not look like is a courier.
     *
     * @return array{0: float, 1: float}|null
     */
    private function coarsen(OrderInterface $cart, PrecogStage $stage): ?array
    {
        $geo = $cart->getShippingAddress()?->getGeo();

        if (null === $geo) {
            return null;
        }

        $latitude = (float) $geo->getLatitude();
        $longitude = (float) $geo->getLongitude();

        $metres = $stage->precisionInMeters();

        $latStep = $metres / 111320;

        // A degree of longitude shrinks towards the poles. The clamp keeps the
        // step finite rather than correct at latitudes nobody delivers to.
        $cos = max(0.01, cos(deg2rad($latitude)));
        $lngStep = $metres / (111320 * $cos);

        return [
            round(round($latitude / $latStep) * $latStep, 6),
            round(round($longitude / $lngStep) * $lngStep, 6),
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function slotOf(OrderInterface $cart): ?array
    {
        $range = $cart->getShippingTimeRange();

        if (null === $range) {
            return null;
        }

        return [
            $range->getLower()->format(\DateTime::ATOM),
            $range->getUpper()->format(\DateTime::ATOM),
        ];
    }
}
