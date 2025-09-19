<?php

namespace AppBundle\Serializer;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Twig\ExpressionLanguageRuntime;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class PricingRuleNormalizer implements NormalizerInterface
{
    public function __construct(
        private readonly ItemNormalizer $itemNormalizer,
        private readonly ObjectNormalizer $symfonyNormalizer,
        private readonly ExpressionLanguageRuntime $expressionLanguageRuntime,
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

        // Add expressionAst field when pricing_rule_set:read group is present
        if (isset($context['groups']) && in_array('pricing_rule_set:read', $context['groups'])) {
            $data['expressionAst'] = null;

            if (!empty($object->getExpression())) {
                try {
                    $parsedExpression = $this->expressionLanguageRuntime->parseExpression($object->getExpression());

                    // Use Symfony's ObjectNormalizer to convert ParsedExpression into a plain json object (not jsonld)
                    $data['expressionAst'] = $this->symfonyNormalizer->normalize($parsedExpression, 'json');

                } catch (\Exception $e) {
                    // If parsing fails, keep expressionAst as null
                    $data['expressionAst'] = null;
                }
            }

            $data['priceAst'] = null;

            if (!empty($object->getPrice())) {
                try {
                    $parsedPrice = $this->expressionLanguageRuntime->parseExpression($object->getPrice());

                    // Use Symfony's ObjectNormalizer to convert ParsedExpression into a plain json object (not jsonld)
                    $data['priceAst'] = $this->symfonyNormalizer->normalize($parsedPrice, 'json');

                } catch (\Exception $e) {
                    // If parsing fails, keep priceAst as null
                    $data['priceAst'] = null;
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
