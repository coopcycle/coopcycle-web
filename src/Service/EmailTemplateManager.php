<?php

namespace AppBundle\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class EmailTemplateManager
{
    /**
     * Customer-facing email types exposed in the editor.
     * Internal emails (admin / owner / dispatcher) are intentionally excluded.
     */
    public const CUSTOMER_EMAILS = [
        'order_created' => [
            'label_key' => 'customize.email_editor.email_type.order_created',
            'variables'  => ['brand_name', 'order_number', 'order_url'],
            'slots'      => ['order_items'],
            'folder'     => 'foodtech',
        ],
        'order_accepted_foodtech_delivery' => [
            'label_key' => 'customize.email_editor.email_type.order_accepted_foodtech_delivery',
            'variables'  => ['brand_name', 'order_number', 'shipping_time_range', 'order_url'],
            'slots'      => ['loopeat_info'],
            'folder'     => 'foodtech',
        ],
        'order_accepted_foodtech_collection' => [
            'label_key' => 'customize.email_editor.email_type.order_accepted_foodtech_collection',
            'variables'  => ['brand_name', 'order_number', 'shipping_time_range', 'vendor_name', 'phone_number', 'order_url'],
            'slots'      => ['loopeat_info'],
            'folder'     => 'foodtech',
        ],
        'order_accepted_lastmile' => [
            'label_key' => 'customize.email_editor.email_type.order_accepted_lastmile',
            'variables'  => ['brand_name', 'order_number', 'public_url_text'],
            'slots'      => [],
            'folder'     => 'lastmile',
        ],
        'order_cancelled' => [
            'label_key' => 'customize.email_editor.email_type.order_cancelled',
            'variables'  => ['brand_name', 'order_number'],
            'slots'      => [],
            'folder'     => 'foodtech',
        ],
        'order_delayed' => [
            'label_key' => 'customize.email_editor.email_type.order_delayed',
            'variables'  => ['brand_name', 'order_number', 'delay'],
            'slots'      => [],
            'folder'     => 'foodtech',
        ],
        'order_payment' => [
            'label_key' => 'customize.email_editor.email_type.order_payment',
            'variables'  => ['brand_name', 'order_number', 'order_url'],
            'slots'      => ['order_items'],
            'folder'     => 'foodtech',
        ],
        'order_receipt' => [
            'label_key' => 'customize.email_editor.email_type.order_receipt',
            'variables'  => ['brand_name', 'order_number'],
            'slots'      => ['order_items'],
            'folder'     => 'foodtech',
        ],
        'task_dropoff_completed' => [
            'label_key' => 'customize.email_editor.email_type.task_dropoff_completed',
            'variables'  => ['brand_name', 'delivery_id', 'tracking_url'],
            'slots'      => [],
            'folder'     => 'lastmile',
        ],
        'task_pickup_completed' => [
            'label_key' => 'customize.email_editor.email_type.task_pickup_completed',
            'variables'  => ['brand_name', 'delivery_id', 'tracking_url'],
            'slots'      => [],
            'folder'     => 'lastmile',
        ],
        'task_dropoff_failed' => [
            'label_key' => 'customize.email_editor.email_type.task_dropoff_failed',
            'variables'  => ['brand_name', 'delivery_id', 'tracking_url'],
            'slots'      => [],
            'folder'     => 'lastmile',
        ],
        'task_pickup_failed' => [
            'label_key' => 'customize.email_editor.email_type.task_pickup_failed',
            'variables'  => ['brand_name', 'delivery_id', 'tracking_url'],
            'slots'      => [],
            'folder'     => 'lastmile',
        ],
    ];

    public const ACCOUNT_EMAILS = [
        'resetting' => [
            'label_key' => 'customize.email_editor.email_type.resetting',
            'variables'  => ['brand_name', 'username', 'confirmation_url'],
            'slots'      => [],
            'folder'     => 'account',
        ],
        'registration' => [
            'label_key' => 'customize.email_editor.email_type.registration',
            'variables'  => ['brand_name', 'username', 'confirmation_url'],
            'slots'      => [],
            'folder'     => 'account',
        ],
    ];

    public const SUPPORTED_LOCALES = [
        'en' => 'English',
        'fr' => 'Français',
        'es' => 'Español',
    ];

    public function __construct(
        private Filesystem $emailTemplatesFilesystem,
        private SettingsManager $settingsManager,
        private TranslatorInterface $translator,
        private TwigEnvironment $twig,
    ) {}

    public function getEmailTypes(string $locale = 'en'): array
    {
        $types = [];
        foreach (array_merge(self::CUSTOMER_EMAILS, self::ACCOUNT_EMAILS) as $type => $meta) {
            $types[$type] = [
                'label'     => $this->translator->trans($meta['label_key'], [], 'messages', $locale),
                'variables' => $meta['variables'],
                'slots'     => $meta['slots'],
                'folder'    => $meta['folder'],
            ];
        }
        return $types;
    }

    public function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    public function isValidType(string $type): bool
    {
        return isset(self::CUSTOMER_EMAILS[$type]) || isset(self::ACCOUNT_EMAILS[$type]);
    }

    public function isValidLocale(string $locale): bool
    {
        return isset(self::SUPPORTED_LOCALES[$locale]);
    }

    private function storagePath(string $type, string $locale): string
    {
        return $locale . '/' . $type . '.mjml';
    }

    private function layoutStoragePath(string $locale): string
    {
        return $locale . '/layout.mjml';
    }

    // ── Layout storage ────────────────────────────────────────────────────────

    /**
     * Returns the custom layout MJML for the given locale, or null if none saved.
     */
    public function getCustomLayout(string $locale = 'en'): ?string
    {
        try {
            $path = $this->layoutStoragePath($locale);
            if ($this->emailTemplatesFilesystem->fileExists($path)) {
                return $this->emailTemplatesFilesystem->read($path);
            }
        } catch (FilesystemException $e) {}

        return null;
    }

    /**
     * Saves a custom layout MJML to S3.
     */
    public function saveLayout(string $locale, string $mjml): void
    {
        $this->emailTemplatesFilesystem->write($this->layoutStoragePath($locale), $mjml);
    }

    /**
     * Deletes the custom layout for the given locale so the default is used again.
     */
    public function deleteLayout(string $locale = 'en'): void
    {
        try {
            $path = $this->layoutStoragePath($locale);
            if ($this->emailTemplatesFilesystem->fileExists($path)) {
                $this->emailTemplatesFilesystem->delete($path);
            }
        } catch (FilesystemException $e) {}
    }

    /**
     * Returns the custom MJML stored in S3 for the given type+locale,
     * or null if no customisation exists.
     */
    public function getCustomTemplate(string $type, string $locale = 'en'): ?string
    {
        if (!$this->isValidType($type)) {
            return null;
        }

        try {
            $path = $this->storagePath($type, $locale);
            if ($this->emailTemplatesFilesystem->fileExists($path)) {
                return $this->emailTemplatesFilesystem->read($path);
            }
        } catch (FilesystemException $e) {
            // fall through
        }

        return null;
    }

    /**
     * Saves a custom MJML template for the given type+locale to S3.
     */
    public function saveTemplate(string $type, string $mjml, string $locale = 'en'): void
    {
        if (!$this->isValidType($type)) {
            throw new \InvalidArgumentException(sprintf('Unknown email type: %s', $type));
        }

        $this->emailTemplatesFilesystem->write($this->storagePath($type, $locale), $mjml);
    }

    /**
     * Deletes the custom template for the given type+locale so the default is used again.
     */
    public function deleteTemplate(string $type, string $locale = 'en'): void
    {
        if (!$this->isValidType($type)) {
            return;
        }

        try {
            $path = $this->storagePath($type, $locale);
            if ($this->emailTemplatesFilesystem->fileExists($path)) {
                $this->emailTemplatesFilesystem->delete($path);
            }
        } catch (FilesystemException $e) {
            // ignore
        }
    }

    /**
     * Returns the theme colours as a flat list for use as colour-picker presets.
     */
    public function getThemePalette(): array
    {
        $t = $this->getThemeColors();
        return array_values(array_unique([
            $t['primary'],
            $t['primary-content'],
            $t['secondary'],
            $t['secondary-content'],
        ]));
    }

    /**
     * Parses the 'theme' JSON from settings and returns colour values with defaults.
     *
     * @return array{primary: string, primary-content: string, secondary: string, secondary-content: string, accent: string, accent-content: string}
     */
    public function getThemeColors(): array
    {
        $defaults = [
            'primary'           => '#10ac84',
            'primary-content'   => 'white',
            'secondary'         => '#eeeeee',
            'secondary-content' => '#ffffff',
            'accent'            => '#10ac84',
            'accent-content'    => 'white',
        ];

        $json = $this->settingsManager->get('theme');
        if (!$json) {
            return $defaults;
        }

        $theme = json_decode($json, true);
        if (!is_array($theme)) {
            return $defaults;
        }

        foreach ($defaults as $key => $default) {
            if (empty($theme[$key])) {
                $theme[$key] = $default;
            }
        }

        return $theme;
    }

    /**
     * Builds fully-rendered MJML for a test email of the given type.
     *
     * Uses the current custom layout + fragment (with locale → en fallback), or
     * defaults. All {{variables}} are replaced with synthetic placeholder values
     * so the admin can see a realistic preview; slot markers are filled with
     * stub content. Returns MJML ready to pass to the MJML renderer.
     */
    public function renderTestTemplate(string $type, string $locale = 'en'): string
    {
        $locales = array_unique([$locale, 'en']);

        // Resolve layout
        $layoutMjml = null;
        foreach ($locales as $l) {
            $layoutMjml = $this->getCustomLayout($l);
            if ($layoutMjml !== null) break;
        }
        $layoutMjml = $layoutMjml ?? $this->getDefaultLayout();

        // Resolve fragment
        $fragmentMjml = null;
        foreach ($locales as $l) {
            $fragmentMjml = $this->getCustomTemplate($type, $l);
            if ($fragmentMjml !== null) break;
        }
        $fragment = $fragmentMjml !== null
            ? $this->ensureFragment($fragmentMjml)
            : $this->getDefaultFragment($type, $locale);

        // Stitch layout + fragment
        $mjml = $this->resolveSlots($layoutMjml, ['content' => $fragment]);

        // Ensure logo URL is browser-safe for rendering (createHtmlMessage() reverses this)
        $mjml = $this->forEditor($mjml);

        // Substitute all {{variables}} with synthetic test values
        $theme    = $this->getThemeColors();
        $testVars = [
            'brand_name'             => $this->settingsManager->get('brand_name') ?? 'CoopCycle',
            'order_number'           => '#1234',
            'order_url'              => 'https://demo.coopcycle.org/order/1234',
            'shipping_time_range'    => '12:00 – 13:00',
            'vendor_name'            => 'Demo Restaurant',
            'phone_number'           => '+33 1 23 45 67 89',
            'public_url_text'        => 'Track your order',
            'tracking_url'           => 'https://demo.coopcycle.org/tracking/5678',
            'delivery_id'            => '#5678',
            'delay'                  => '15 minutes',
            'username'               => 'john_doe',
            'confirmation_url'       => 'https://demo.coopcycle.org/resetting/reset/abc123',
            'primary_color'          => $theme['primary'],
            'primary_content_color'  => $theme['primary-content'],
        ];
        $mjml = $this->substituteVariables($mjml, $testVars);

        // Fill any remaining slot markers with stubs
        return $this->resolveSlots($mjml, $this->getTestSlotStubs($type));
    }

    private function getTestSlotStubs(string $type): array
    {
        $stubs = [];
        $allEmails = array_merge(self::CUSTOMER_EMAILS, self::ACCOUNT_EMAILS);
        foreach ($allEmails[$type]['slots'] ?? [] as $slot) {
            $stubs[$slot] = match ($slot) {
                'order_items' =>
                    '<mj-text align="left" line-height="20px">' .
                    '<strong>1× Burger – €12.00</strong><br />2× Frites – €5.00' .
                    '</mj-text>',
                default => '',
            };
        }
        return $stubs;
    }

    /**
     * Generates the default layout MJML (full document) with a content slot.
     * This is what the layout editor starts with when no custom layout is saved.
     */
    public function getDefaultLayout(): string
    {
        return $this->twig->render('emails/layout_with_slot.mjml.twig', [
            'logo_src'   => '/img/logo.png',
            'brand_name' => '{{brand_name}}',
        ]);
    }

    /**
     * Replaces the email-only cid:logo URI with a browser-accessible path so the
     * logo renders correctly in the GrapeJS editor canvas.
     * Safe to call multiple times; a no-op when the URL is already set correctly.
     */
    public function forEditor(string $mjml): string
    {
        return str_replace('"cid:logo"', '"/img/logo.png"', $mjml);
    }

    /**
     * Returns the fragment for the given type: custom (with locale→en fallback) or the default.
     * Used by the `email_fragment()` Twig function so bundle email templates pick up customisations.
     */
    public function getFragment(string $type, string $locale = 'en'): string
    {
        $locales = array_unique([$locale, 'en']);
        foreach ($locales as $l) {
            $custom = $this->getCustomTemplate($type, $l);
            if ($custom !== null) {
                return $this->ensureFragment($custom);
            }
        }
        return $this->getDefaultFragment($type, $locale);
    }

    /**
     * Returns the inner column content (fragment) for the given email type.
     * Contains only mj-text / mj-button / mj-raw children — no mj-section or
     * mj-column wrapper, because the layout now owns that wrapper.
     */
    public function getDefaultFragment(string $type, string $locale = 'en'): string
    {
        $theme = $this->getThemeColors();

        return $this->twig->render("emails/fragments/{$type}.mjml.twig", [
            'locale'                => $locale,
            'primary_color'         => $theme['primary'],
            'primary_content_color' => $theme['primary-content'],
            'order_url'             => '{{order_url}}',
            'tracking_url'          => '{{tracking_url}}',
            'shipping_time_range'   => '{{shipping_time_range}}',
            'vendor_name'           => '{{vendor_name}}',
            'phone_number'          => '{{phone_number}}',
            'public_url_text'       => '{{public_url_text}}',
            'confirmation_url'      => '{{confirmation_url}}',
        ]);
    }

    /**
     * Normalises a stored template to a plain fragment (mj-text / mj-button /
     * mj-raw children only — no mj-section / mj-column / mjml wrappers).
     *
     * Handles three legacy formats:
     *   1. Full MJML document  → extract mj-body → strip section+column
     *   2. <mj-section><mj-column>…  → strip section+column
     *   3. Already a plain fragment  → return as-is
     */
    public function ensureFragment(string $mjml): string
    {
        $mjml = trim($mjml);

        // 1. Full MJML document
        if (preg_match('/^<mjml/i', $mjml)) {
            if (preg_match('/<mj-body[^>]*>([\s\S]*?)<\/mj-body>/i', $mjml, $m)) {
                $mjml = trim($m[1]);
            } else {
                return $mjml;
            }
        }

        // 2. Old-style fragment wrapped in <mj-section><mj-column>
        if (preg_match('/^<mj-section/i', $mjml)) {
            if (preg_match('/<mj-column[^>]*>([\s\S]*?)<\/mj-column>/i', $mjml, $m)) {
                return trim($m[1]);
            }
        }

        return $mjml;
    }

    /**
     * Builds the full MJML sent to the GrapeJS editor when editing a fragment.
     *
     * The layout is stitched with the fragment, but the content area is wrapped
     * in two locked boundary markers (content_start / content_end). GrapeJS
     * round-trips those markers verbatim, and the JS uses them to extract only
     * the fragment when saving — ignoring changes made to layout sections.
     *
     * @return array{mjml: string, is_custom: bool}
     */
    public function buildEditorMjml(string $type, string $locale = 'en'): array
    {
        $locales = array_unique([$locale, 'en']);

        $layoutMjml = null;
        foreach ($locales as $l) {
            $layoutMjml = $this->getCustomLayout($l);
            if ($layoutMjml !== null) break;
        }
        $layoutMjml = $layoutMjml ?? $this->getDefaultLayout();

        $customFragment = null;
        foreach ($locales as $l) {
            $customFragment = $this->getCustomTemplate($type, $l);
            if ($customFragment !== null) break;
        }
        $isCustom = $customFragment !== null;
        $fragment = $customFragment !== null
            ? $this->ensureFragment($customFragment)
            : $this->getDefaultFragment($type, $locale);

        $fragmentWithMarkers =
            '<mj-raw data-slot="content_start"></mj-raw>' . "\n" .
            $fragment . "\n" .
            '<mj-raw data-slot="content_end"></mj-raw>';

        $stitched = $this->resolveSlots($layoutMjml, ['content' => $fragmentWithMarkers]);

        return ['mjml' => $this->forEditor($stitched), 'is_custom' => $isCustom];
    }

    /**
     * Renders the custom email for the given type by composing the layout and fragment.
     *
     * Logic:
     *  - custom layout + custom fragment → stitch both
     *  - custom layout + no fragment     → stitch layout with default fragment
     *  - no layout     + custom fragment → stitch default layout with fragment
     *  - neither                         → return null (fall back to Twig default)
     *
     * Variable substitution runs on the fully-stitched MJML so both layout and
     * fragment can use {{variable}} placeholders.
     */
    public function renderCustomTemplate(string $type, array $variables, string $locale = 'en'): ?string
    {
        $locales = array_unique([$locale, 'en']);

        // Resolve custom layout (locale → en fallback)
        $layoutMjml = null;
        foreach ($locales as $l) {
            $layoutMjml = $this->getCustomLayout($l);
            if ($layoutMjml !== null) break;
        }

        // Resolve custom fragment (locale → en fallback)
        $fragmentMjml = null;
        foreach ($locales as $l) {
            $fragmentMjml = $this->getCustomTemplate($type, $l);
            if ($fragmentMjml !== null) break;
        }

        // Neither customised → use Twig default
        if ($layoutMjml === null && $fragmentMjml === null) {
            return null;
        }

        $layout   = $layoutMjml ?? $this->getDefaultLayout();
        $fragment = $fragmentMjml !== null
            ? $this->ensureFragment($fragmentMjml)   // normalise legacy full-MJML saves
            : $this->getDefaultFragment($type, $locale);

        // Stitch fragment into layout's content slot, then resolve per-email dynamic slots
        $mjml = $this->resolveSlots($layout, ['content' => $fragment]);

        $theme    = $this->getThemeColors();
        $defaults = [
            'brand_name'               => $this->settingsManager->get('brand_name') ?? '',
            'primary_color'            => $theme['primary'],
            'primary_content_color'    => $theme['primary-content'],
        ];

        return $this->substituteVariables($mjml, array_merge($defaults, $variables));
    }

    /**
     * Replaces slot markers with pre-rendered MJML snippets before compilation.
     * Must be called BEFORE passing the MJML to the compiler.
     *
     * @param array<string,string> $slots Map of slot name → MJML snippet string
     */
    public function resolveSlots(string $mjml, array $slots): string
    {
        foreach ($slots as $slotName => $slotMjml) {
            $mjml = preg_replace(
                '/<mj-raw\s+data-slot="' . preg_quote($slotName, '/') . '"\s*(?:\/>|>\s*<\/mj-raw>)/i',
                $slotMjml,
                $mjml
            );
        }
        return $mjml;
    }

    private function substituteVariables(string $template, array $variables): string
    {
        $search  = [];
        $replace = [];
        foreach ($variables as $key => $value) {
            $search[]  = '{{' . $key . '}}';
            $replace[] = (string) $value;
        }
        return str_replace($search, $replace, $template);
    }
}
