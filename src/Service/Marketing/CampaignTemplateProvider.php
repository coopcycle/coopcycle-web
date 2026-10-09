<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Service\SettingsManager;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The starting point for a campaign to a given segment.
 *
 * The whole premise of guided automation is that an admin shouldn't have to
 * know what to say to "hibernating" customers: the platform proposes a
 * strategy and they edit it. So every segment with a known strategy ships
 * with a subject and a body already written.
 *
 * Segments without an agreed strategy still get a campaign -- just a blank
 * one. Better than hiding them.
 *
 * Everything here is seeded in the *instance* locale, not the admin's. The
 * subject and body are read by customers, so they should be in the language
 * the instance serves, whatever language the admin happens to be using the
 * back office in. The name follows the same locale simply so a campaign
 * isn't half in one language and half in another.
 */
class CampaignTemplateProvider
{
    /**
     * Segments the platform has an opinion about, in the order an admin is
     * most likely to want them.
     */
    public const SEGMENTS_WITH_STRATEGY = [
        'champions',
        'loyal_customers',
        'potential_loyalists',
        'at_risk',
        'hibernating',
        'lost',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SettingsManager $settingsManager,
        private readonly string $locale,
    ) {
    }

    public function hasStrategy(string $segment): bool
    {
        return in_array($segment, self::SEGMENTS_WITH_STRATEGY, true);
    }

    public function getName(string $segment): string
    {
        return $this->translator->trans('marketing.campaign.template.' . $segment . '.name', [
            '%segment%' => $this->translator->trans('rfm.segment.' . $segment, [], null, $this->locale),
        ], null, $this->locale);
    }

    public function getSubject(string $segment): string
    {
        if (!$this->hasStrategy($segment)) {
            return '';
        }

        return $this->translator->trans('marketing.campaign.template.' . $segment . '.subject', [
            '%brand_name%' => (string) $this->settingsManager->get('brand_name'),
        ], null, $this->locale);
    }

    /**
     * A whole MJML document, because that's what the editor opens and what
     * the renderer turns into the HTML actually sent.
     */
    public function getBodyMjml(string $segment): string
    {
        $body = $this->hasStrategy($segment)
            ? $this->translator->trans('marketing.campaign.template.' . $segment . '.body', [
                '%brand_name%' => (string) $this->settingsManager->get('brand_name'),
            ], null, $this->locale)
            : '';

        $paragraphs = '';

        foreach (preg_split('/\n{2,}/', trim($body)) ?: [] as $paragraph) {
            if ('' === trim($paragraph)) {
                continue;
            }

            $paragraphs .= sprintf(
                "      <mj-text font-size=\"15px\" line-height=\"22px\">%s</mj-text>\n",
                htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE)
            );
        }

        if ('' === $paragraphs) {
            $paragraphs = "      <mj-text font-size=\"15px\" line-height=\"22px\"></mj-text>\n";
        }

        return <<<MJML
        <mjml>
          <mj-body background-color="#f6f6f6">
            <mj-section background-color="#ffffff">
              <mj-column>
        $paragraphs      </mj-column>
            </mj-section>
          </mj-body>
        </mjml>
        MJML;
    }
}
