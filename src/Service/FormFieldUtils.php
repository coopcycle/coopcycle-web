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

        // docs path exists
        if ($localizedDocsPath !== $docsPath) {
            return [
                'label' => '%label%. <a href="https://docs.coopcycle.org%docs_path%" target="_blank" rel="noopener">%docs_label% <i class="fa fa-external-link"></i></a>',
                'label_translation_parameters' => [
                    '%label%' => $this->translator->trans($label),
                    '%docs_path%' => $this->translator->trans($docsPath),
                    '%docs_label%' => $this->translator->trans('docs.label'),
                ],
                'label_html' => true,
            ];
        } else {
            return [
                'label' => $label,
            ];
        }
    }
}
