<?php

namespace AppBundle\Twig;

use Recurr\Rule;
use Recurr\Transformer\TextTransformer;
use Recurr\Transformer\Translator;
use Twig\Extension\RuntimeExtensionInterface;

class RecurrRuleFormatResolver implements RuntimeExtensionInterface
{
    public function format($context, Rule $rule)
    {
        // @phpstan-ignore-next-line
        if (null === $rule->getStartDate()) {
            // Set start date to current date if not set
            // this is required for the text transformer
            $rule->setStartDate(new \DateTime());
        }
        $locale = $context['app']->getRequest()->getLocale();
        $textTransformer = new TextTransformer(new Translator($locale));

        return $textTransformer->transform($rule);
    }
}
