<?php

namespace AppBundle\Service\GiftCard;

use AppBundle\Service\SettingsManager;

/**
 * The same two-gate arrangement as LoyaltyProgramStatus and
 * ReferralProgramStatus:
 *
 * - GIFT_CARDS_ENABLED (env var): a deployment-time switch. Off means the
 *   feature doesn't exist at all, not even the admin screens.
 * - gift_cards_active (Craue setting, admin-toggleable): the runtime switch,
 *   defaulting to *off* even when the env var is on.
 *
 * The runtime gate matters more here than it does for loyalty points. Turning
 * gift cards on changes how a coop gets paid on every order a card touches:
 * the charge moves to the platform account and the restaurant is paid by
 * transfer afterwards. That is not something an env var should be able to
 * switch on for live customers before anyone has set the amounts, written the
 * legal mention, or told the restaurants.
 *
 * isActive() is what every customer-facing and money-moving path checks.
 */
class GiftCardStatus
{
    public const DEFAULT_MIN_AMOUNT = 1000;
    public const DEFAULT_MAX_AMOUNT = 25000;

    /**
     * Zero means cards never lapse, and that is the default: several EU
     * countries restrict short expiry periods, and some US states require
     * unused balances to be handed to the state rather than kept. "Never" is
     * the only answer that is safe everywhere without advice.
     */
    public const DEFAULT_VALIDITY_MONTHS = 0;

    public const EXPIRY_POLICY_NEVER = 'never';
    public const EXPIRY_POLICY_FORFEIT = 'expire_and_forfeit';

    public function __construct(
        private readonly bool $envEnabled,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function isEnvEnabled(): bool
    {
        return $this->envEnabled;
    }

    public function isActive(): bool
    {
        return $this->envEnabled && $this->settingsManager->getBoolean('gift_cards_active');
    }

    /**
     * Whether a card may be tied to a single restaurant.
     *
     * Separate from isActive(), and off by default, because scoping is the
     * one part of the feature with a VAT question behind it: restricting a
     * card to one vendor can, in a narrow case, make it a single-purpose
     * voucher, on which VAT falls due at sale. Platform-wide cards never
     * raise the question.
     */
    public function isScopingEnabled(): bool
    {
        return $this->isActive() && $this->settingsManager->getBoolean('gift_card_scoping_enabled');
    }

    public function getExpiryPolicy(): string
    {
        $policy = $this->settingsManager->get('gift_card_expiry_policy');

        return in_array($policy, [self::EXPIRY_POLICY_NEVER, self::EXPIRY_POLICY_FORFEIT], true)
            ? $policy
            : self::EXPIRY_POLICY_NEVER;
    }

    public function getValidityMonths(): int
    {
        if (self::EXPIRY_POLICY_NEVER === $this->getExpiryPolicy()) {
            return 0;
        }

        return (int) ($this->settingsManager->get('gift_card_validity_months')
            ?? self::DEFAULT_VALIDITY_MONTHS);
    }

    /**
     * When a card bought now would lapse, or null if it never would.
     */
    public function resolveExpiresAt(?\DateTime $from = null): ?\DateTime
    {
        $months = $this->getValidityMonths();

        if ($months < 1) {
            return null;
        }

        $expiresAt = clone ($from ?? new \DateTime());
        $expiresAt->modify(sprintf('+%d months', $months));

        return $expiresAt;
    }

    public function getMinAmount(): int
    {
        $amount = (int) ($this->settingsManager->get('gift_card_min_amount') ?? 0);

        return $amount > 0 ? $amount : self::DEFAULT_MIN_AMOUNT;
    }

    public function getMaxAmount(): int
    {
        $amount = (int) ($this->settingsManager->get('gift_card_max_amount') ?? 0);

        return $amount > 0 ? $amount : self::DEFAULT_MAX_AMOUNT;
    }

    /**
     * The amounts offered on the purchase page, as a list of cents. Admins
     * enter them comma-separated; anything outside the min/max is dropped
     * rather than silently offered.
     */
    public function getDenominations(): array
    {
        $raw = $this->settingsManager->get('gift_card_denominations');

        if (empty($raw)) {
            return [];
        }

        $min = $this->getMinAmount();
        $max = $this->getMaxAmount();

        $amounts = array_map(
            fn (string $value): int => (int) trim($value),
            explode(',', $raw)
        );

        $amounts = array_filter(
            $amounts,
            fn (int $amount): bool => $amount >= $min && $amount <= $max
        );

        sort($amounts);

        return array_values(array_unique($amounts));
    }

    /**
     * The mention a purchase receipt has to carry, e.g. "multi-purpose
     * voucher, art. 256 ter CGI". Country- and locale-dependent, so it is
     * settings-driven rather than hardcoded -- CoopCycle runs well outside
     * the EU, where this framework doesn't apply at all.
     */
    public function getLegalMention(): ?string
    {
        $mention = $this->settingsManager->get('gift_card_legal_mention');

        return empty($mention) ? null : $mention;
    }
}
