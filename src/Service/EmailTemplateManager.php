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
            'slots'      => [],
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
     * Returns a complete MJML document to use as the starting point in the editor.
     * Uses the existing email translations for the given locale.
     */
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

    public function getDefaultMjml(string $type, string $locale = 'en'): string
    {
        $theme          = $this->getThemeColors();
        $brandName      = $this->settingsManager->get('brand_name') ?? '{{brand_name}}';
        $primaryColor   = $theme['primary'];
        $primaryContent = $theme['primary-content'];
        $bgColor        = $theme['secondary'];
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

        $c = $contents[$type] ?? ['heading' => '', 'body' => '', 'cta' => null];

        // Escape body for MJML (strip newlines that break tag nesting)
        $body = trim(preg_replace('/\s+/', ' ', $c['body']));

        $ctaMjml = '';
        if ($c['cta'] !== null) {
            $ctaMjml = sprintf(
                "\n        <mj-button href=\"%s\" background-color=\"%s\" color=\"%s\" font-family=\"Raleway, Arial, sans-serif\">%s</mj-button>",
                htmlspecialchars($c['cta']['href'], ENT_QUOTES),
                htmlspecialchars($primaryColor, ENT_QUOTES),
                htmlspecialchars($primaryContent, ENT_QUOTES),
                htmlspecialchars($c['cta']['label'], ENT_QUOTES)
            );
        }

        $heading = htmlspecialchars($c['heading'], ENT_QUOTES);

        // Slot markers for types that support dynamic content blocks
        $slots = self::CUSTOMER_EMAILS[$type]['slots'] ?? [];
        $slotsMjml = '';
        foreach ($slots as $slotName) {
            $slotsMjml .= "\n        <mj-raw data-slot=\"{$slotName}\"></mj-raw>";
        }

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
    <mj-section background-color="{$contentBgColor}">
      <mj-column>
        <mj-text align="left" line-height="24px">
          <h3>{$heading}</h3>
          <p>{$body}</p>
        </mj-text>{$slotsMjml}{$ctaMjml}
      </mj-column>
    </mj-section>
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
     * Renders a stored custom MJML by substituting the given variable map.
     * Falls back to the next locale in the chain (given locale → 'en' → null).
     * Returns null if no custom template exists for this type in any fallback locale.
     */
    public function renderCustomTemplate(string $type, array $variables, string $locale = 'en'): ?string
    {
        // Try requested locale first, then fall back to English
        $locales = array_unique([$locale, 'en']);

        foreach ($locales as $l) {
            $template = $this->getCustomTemplate($type, $l);
            if ($template !== null) {
                $theme    = $this->getThemeColors();
                $defaults = [
                    'primary_color'            => $theme['primary'],
                    'primary_content_color'    => $theme['primary-content'],
                    'background_color'         => $theme['secondary'],
                    'content_background_color' => $theme['secondary-content'],
                ];
                return $this->substituteVariables($template, array_merge($defaults, $variables));
            }
        }

        return null;
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
