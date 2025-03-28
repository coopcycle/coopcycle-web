<?php

namespace AppBundle\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class FormFieldUtils
{

    public function __construct(
        private TranslatorInterface $translator,
    )
    {
    }

    function getLabelWithLinkToDocs($label, $docsPath)
    {
        $localizedDocsPath = $this->translator->trans($docsPath);

        // docs path provided in the translations
        if ($localizedDocsPath !== $docsPath) {
            $url = 'https://docs.coopcycle.org' . $localizedDocsPath;
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return [
                    'label' => '%label%. <a href="%url%" title="%hint%" target="_blank" rel="noopener"><i class="fa fa-question-circle"></i></a>',
                    'label_translation_parameters' => [
                        '%label%' => $this->translator->trans($label),
                        '%url%' => $url,
                        '%hint%' => $this->translator->trans('visitDocumentation.label'),
                    ],
                    'label_html' => true,
                ];
            }
        }

        return [
            'label' => $label,
        ];
    }
}
