<?php

namespace AppBundle\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        ],
        'order_accepted' => [
            'label_key' => 'customize.email_editor.email_type.order_accepted',
            'variables'  => ['brand_name', 'order_number', 'order_url'],
            'slots'      => ['loopeat_info'],
        ],
        'order_cancelled' => [
            'label_key' => 'customize.email_editor.email_type.order_cancelled',
            'variables'  => ['brand_name', 'order_number'],
            'slots'      => [],
        ],
        'order_delayed' => [
            'label_key' => 'customize.email_editor.email_type.order_delayed',
            'variables'  => ['brand_name', 'order_number', 'delay'],
            'slots'      => [],
        ],
        'order_payment' => [
            'label_key' => 'customize.email_editor.email_type.order_payment',
            'variables'  => ['brand_name', 'order_number'],
            'slots'      => ['order_items'],
        ],
        'order_receipt' => [
            'label_key' => 'customize.email_editor.email_type.order_receipt',
            'variables'  => ['brand_name', 'order_number'],
            'slots'      => ['order_items'],
        ],
        'task_completed' => [
            'label_key' => 'customize.email_editor.email_type.task_completed',
            'variables'  => ['brand_name', 'delivery_id', 'tracking_url'],
            'slots'      => [],
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
    ) {}

    public function getEmailTypes(string $locale = 'en'): array
    {
        $types = [];
        foreach (self::CUSTOMER_EMAILS as $type => $meta) {
            $types[$type] = [
                'label'     => $this->translator->trans($meta['label_key'], [], 'messages', $locale),
                'variables' => $meta['variables'],
                'slots'     => $meta['slots'],
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
        return isset(self::CUSTOMER_EMAILS[$type]);
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
     * Parses the 'theme' JSON from settings and returns colour values with defaults.
     *
     * @return array{primary: string, primary-content: string, secondary: string, secondary-content: string, accent: string, accent-content: string}
     */
    private function getThemeColors(): array
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
     * Returns the current global email colour settings with their defaults.
     * Keys use the DaisyUI theme naming from the 'theme' JSON in settings.
     */
    public function getEmailStyleSettings(): array
    {
        $theme = $this->getThemeColors();

        return [
            'primary'           => $theme['primary'],
            'primary-content'   => $theme['primary-content'],
            'secondary'         => $theme['secondary'],
            'secondary-content' => $theme['secondary-content'],
        ];
    }

    /**
     * Generates the default layout MJML (full document) with a content slot.
     * This is what the layout editor starts with when no custom layout is saved.
     */
    public function getDefaultLayout(): string
    {
        $theme     = $this->getThemeColors();
        $brandName = htmlspecialchars($this->settingsManager->get('brand_name') ?? '{{brand_name}}', ENT_QUOTES);
        $bgColor   = htmlspecialchars($theme['secondary'], ENT_QUOTES);

        return <<<MJML
<mjml>
  <mj-head>
    <mj-font name="Raleway" href="https://fonts.googleapis.com/css?family=Raleway:400,700" />
    <mj-font name="Open Sans" href="https://fonts.googleapis.com/css?family=Open+Sans:400,700" />
    <mj-attributes>
      <mj-text align="center" color="#555" />
      <mj-all font-family="'Open Sans', Arial, sans-serif" />
    </mj-attributes>
  </mj-head>
  <mj-body background-color="{$bgColor}">
    <mj-section>
      <mj-column>
        <mj-text font-family="Raleway, Arial, sans-serif" align="left">
          <h2>{$brandName}</h2>
        </mj-text>
      </mj-column>
    </mj-section>
    <mj-raw data-slot="content"></mj-raw>
    <mj-section>
      <mj-column>
        <mj-text align="center" font-size="12px" color="#999999">
          Powered by CoopCycle
        </mj-text>
      </mj-column>
    </mj-section>
  </mj-body>
</mjml>
MJML;
    }

    /**
     * Returns just the inner mj-section blocks (fragment) for the given email type.
     * No <mjml>/<mj-head>/<mj-body> wrapper — these are stitched in by the layout.
     */
    public function getDefaultFragment(string $type, string $locale = 'en'): string
    {
        $theme          = $this->getThemeColors();
        $primaryColor   = $theme['primary'];
        $primaryContent = $theme['primary-content'];
        $contentBgColor = $theme['secondary-content'];

        $t = fn(string $key, array $params = []) =>
            $this->translator->trans($key, $params, 'emails', $locale);

        $contents = [
            'order_created' => [
                'heading' => $t('order.created.subject'),
                'body'    => $t('order.created.body'),
                'cta'     => ['label' => $t('order.view'), 'href' => '{{order_url}}'],
            ],
            'order_accepted' => [
                'heading' => $t('order.accepted.subject'),
                'body'    => $t('order.accepted.body.intro'),
                'cta'     => ['label' => $t('order.view'), 'href' => '{{order_url}}'],
            ],
            'order_cancelled' => [
                'heading' => $t('order.cancelled.subject'),
                'body'    => $t('order.cancelled.body.intro'),
                'cta'     => null,
            ],
            'order_delayed' => [
                'heading' => $t('order.delayed.subject'),
                'body'    => $t('order.delayed.body'),
                'cta'     => null,
            ],
            'order_payment' => [
                'heading' => $t('order.payment.subject'),
                'body'    => $t('order.payment.body'),
                'cta'     => null,
            ],
            'order_receipt' => [
                'heading' => $t('order.receipt.subject'),
                'body'    => $t('order.receipt.body'),
                'cta'     => null,
            ],
            'task_completed' => [
                'heading' => $t('task.dropoff.done.subject'),
                'body'    => $t('task.dropoff.done.body'),
                'cta'     => null,
            ],
        ];

        $c       = $contents[$type] ?? ['heading' => '', 'body' => '', 'cta' => null];
        $body    = trim(preg_replace('/\s+/', ' ', $c['body']));
        $heading = htmlspecialchars($c['heading'], ENT_QUOTES);

        $ctaMjml = '';
        if ($c['cta'] !== null) {
            $ctaMjml = sprintf(
                "\n    <mj-button href=\"%s\" background-color=\"%s\" color=\"%s\" font-family=\"Raleway, Arial, sans-serif\">%s</mj-button>",
                htmlspecialchars($c['cta']['href'], ENT_QUOTES),
                htmlspecialchars($primaryColor, ENT_QUOTES),
                htmlspecialchars($primaryContent, ENT_QUOTES),
                htmlspecialchars($c['cta']['label'], ENT_QUOTES)
            );
        }

        $slots     = self::CUSTOMER_EMAILS[$type]['slots'] ?? [];
        $slotsMjml = '';
        foreach ($slots as $slotName) {
            $slotsMjml .= "\n    <mj-raw data-slot=\"{$slotName}\"></mj-raw>";
        }

        $bgAttr = htmlspecialchars($contentBgColor, ENT_QUOTES);

        return <<<MJML
<mj-section background-color="{$bgAttr}">
  <mj-column>
    <mj-text align="left" line-height="24px">
      <h3>{$heading}</h3>
      <p>{$body}</p>
    </mj-text>{$slotsMjml}{$ctaMjml}
  </mj-column>
</mj-section>
MJML;
    }

    /**
     * If the given string is a legacy full MJML document (starts with <mjml>),
     * extracts and returns only the mj-body content. Otherwise returns as-is.
     * Used to normalise templates saved before the layout/fragment split.
     */
    public function ensureFragment(string $mjml): string
    {
        if (!preg_match('/^\s*<mjml/i', $mjml)) {
            return $mjml;
        }
        if (preg_match('/<mj-body[^>]*>([\s\S]*?)<\/mj-body>/i', $mjml, $m)) {
            return trim($m[1]);
        }
        return $mjml;
    }

    /**
     * Wraps a fragment (inner mj-section blocks) in a full MJML shell that
     * GrapeJS can render. The mj-head and body background are taken from the
     * current layout (custom or default) so fonts and colours match.
     */
    public function buildFragmentShell(string $fragment, string $locale = 'en'): string
    {
        $locales   = array_unique([$locale, 'en']);
        $layoutMjml = null;
        foreach ($locales as $l) {
            $layoutMjml = $this->getCustomLayout($l);
            if ($layoutMjml !== null) break;
        }
        $layoutMjml = $layoutMjml ?? $this->getDefaultLayout();

        $head    = '';
        if (preg_match('/<mj-head[\s\S]*?<\/mj-head>/i', $layoutMjml, $m)) {
            $head = $m[0];
        }

        $bgColor = '#eeeeee';
        if (preg_match('/<mj-body[^>]*background-color=["\']([^"\']+)["\'][^>]*>/i', $layoutMjml, $m)) {
            $bgColor = $m[1];
        }

        return <<<MJML
<mjml>
  {$head}
  <mj-body background-color="{$bgColor}">
{$fragment}
  </mj-body>
</mjml>
MJML;
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
            'primary_color'            => $theme['primary'],
            'primary_content_color'    => $theme['primary-content'],
            'background_color'         => $theme['secondary'],
            'content_background_color' => $theme['secondary-content'],
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
