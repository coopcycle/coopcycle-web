<?php

namespace AppBundle\Serializer;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use AppBundle\Pricing\RuleHumanizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class PricingRuleNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ItemNormalizer $itemNormalizer,
        private readonly ObjectNormalizer $symfonyNormalizer,
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly RuleHumanizer $ruleHumanizer,
    ) {}

    /**
     * @param PricingRule $object
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->itemNormalizer->normalize($object, $format, $context);

        if (!is_array($data)) {
            return $data;
        }

        // Generate a default name if none is defined
        if (isset($context['groups']) && in_array('pricing_deliveries', $context['groups'])) {
            // Generate a default name if none is defined
            if (is_null($data['name']) || '' === trim($data['name'])) {
                $data['name'] = $this->ruleHumanizer->humanize($object);
            }
        }
        
        // Add expressionAst field when pricing_rule_set:read group is present
        if (isset($context['groups']) && in_array('pricing_rule_set:read', $context['groups'])) {
            $data['expressionAst'] = null;

            if (!empty($object->getExpression())) {
                $parsedExpression = $this->expressionLanguage->parseRuleExpression($object->getExpression());

                if (null === $parsedExpression) {
                    // If parsing fails, keep expressionAst as null
                    $data['expressionAst'] = null;
                } else {
                    // Use Symfony's ObjectNormalizer to convert ParsedExpression into a plain json object (not jsonld)
                    $data['expressionAst'] = $this->symfonyNormalizer->normalize($parsedExpression, 'json');
                }
            }

            $data['priceAst'] = null;

            if (!empty($object->getPrice())) {
                $parsedPrice = $this->expressionLanguage->parsePrice($object->getPrice());

                if (null === $parsedPrice) {
                    // If parsing fails, keep priceAst as null
                    $data['priceAst'] = null;
                } else {
                    // Use Symfony's ObjectNormalizer to convert ParsedExpression into a plain json object (not jsonld)
                    $data['priceAst'] = $this->symfonyNormalizer->normalize($parsedPrice, 'json');
                }
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof PricingRule;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PricingRule::class => true, // supports*() call result is cached
        ];
    }
}
