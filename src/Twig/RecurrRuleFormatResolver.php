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
        if (null === $rule->getStartDate()) {
            return 'Invalid recurrence rule: missing start date.';
        }
        $locale = $context['app']->getRequest()->getLocale();
        $textTransformer = new TextTransformer(new Translator($locale));

        return $textTransformer->transform($rule);
    }
}
