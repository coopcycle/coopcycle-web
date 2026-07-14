<?php

namespace AppBundle\Service\Shift\Compliance;

use AppBundle\Service\SettingsManager;

/**
 * Stored shape (settings key "shift_legal_config"):
 *   { "template": "ccn_transport_fr" | null, "rules": { <overrides> } }
 *
 * template null = legal constraints disabled. The effective rules are the
 * template's defaults with the admin's overrides applied; an override
 * explicitly set to null disables that single rule.
 */
final class LegalConfig
{
    private const SETTING = 'shift_legal_config';

    public function __construct(
        private readonly SettingsManager $settingsManager)
    {
    }

    public function getTemplate(): ?string
    {
        $config = $this->read();

        $template = $config['template'] ?? null;

        return is_string($template) && ConstraintTemplates::has($template) ? $template : null;
    }

    /**
     * @return array<string, float|int|null> the stored overrides only
     */
    public function getOverrides(): array
    {
        $config = $this->read();

        $overrides = $config['rules'] ?? [];

        return is_array($overrides) ? $this->sanitizeRules($overrides) : [];
    }

    /**
     * Template defaults merged with overrides. Empty when disabled.
     *
     * @return array<string, float|int>
     */
    public function getEffectiveRules(): array
    {
        $template = $this->getTemplate();
        if (null === $template) {
            return [];
        }

        $rules = array_merge(ConstraintTemplates::rules($template), $this->getOverrides());

        return array_filter($rules, fn ($value) => is_numeric($value) && $value > 0);
    }

    public function save(?string $template, array $overrides): void
    {
        if (null !== $template && !ConstraintTemplates::has($template)) {
            throw new \InvalidArgumentException(sprintf('Unknown legal constraints template "%s"', $template));
        }

        $this->settingsManager->set(self::SETTING, json_encode([
            'template' => $template,
            'rules' => $this->sanitizeRules($overrides),
        ]));
        $this->settingsManager->flush();
    }

    /**
     * Keeps only known rule keys, with numeric values (or null = disabled).
     *
     * @return array<string, float|int|null>
     */
    private function sanitizeRules(array $rules): array
    {
        $sanitized = [];
        foreach (ConstraintTemplates::RULE_KEYS as $key) {
            if (!array_key_exists($key, $rules)) {
                continue;
            }
            $value = $rules[$key];
            if (null === $value) {
                $sanitized[$key] = null;
            } elseif (is_numeric($value) && $value > 0) {
                $sanitized[$key] = $value + 0;
            }
        }

        return $sanitized;
    }

    private function read(): array
    {
        $json = $this->settingsManager->get(self::SETTING);
        if (empty($json)) {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
