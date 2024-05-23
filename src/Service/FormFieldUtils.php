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
                    'label' => '%label%. <a href="%url%" target="_blank" rel="noopener">%docs_label% <i class="fa fa-external-link"></i></a>',
                    'label_translation_parameters' => [
                        '%label%' => $this->translator->trans($label),
                        '%url%' => $url,
                        '%docs_label%' => $this->translator->trans('docs.label'),
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
