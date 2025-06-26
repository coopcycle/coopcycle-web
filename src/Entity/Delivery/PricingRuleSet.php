<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Action\PricingRuleSet\Applications;
use AppBundle\Api\State\ValidationAwareRemoveProcessor;
use AppBundle\Validator\Constraints\PricingRuleSetDelete as AssertCanDelete;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['pricing_rule_set:read']],
        ),
        new Put(
            normalizationContext: ['groups' => ['pricing_rule_set:read']],
            denormalizationContext: ['groups' => ['pricing_rule_set:write']],
        ),
        new Delete(
            validationContext: ['groups' => ['deleteValidation']],
            processor: ValidationAwareRemoveProcessor::class,
        ),
        new Get(
            uriTemplate: '/pricing_rule_sets/{id}/applications',
            controller: Applications::class,
            openapiContext: ['summary' => 'Get the objects to which this pricing rule set is applied'],
        ),
        new Post(
            normalizationContext: ['groups' => ['pricing_rule_set:read']],
            denormalizationContext: ['groups' => ['pricing_rule_set:write']],
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['pricing_rule_set:read']],
        ),
    ],
    security: "is_granted('ROLE_ADMIN')"
)]
#[AssertCanDelete(groups: ['deleteValidation'])]
class PricingRuleSet
{
    /**
     * @var int
     */
    #[Groups(['pricing_rule_set:read'])]
    protected $id;

    #[Assert\Valid]
    #[Groups(['pricing_rule_set:read', 'pricing_rule_set:write'])]
    protected $rules;

    #[Groups(['pricing_rule_set:read', 'pricing_rule_set:write'])]
    protected $name;

    #[Groups(['pricing_rule_set:read', 'pricing_rule_set:write'])]
    protected $strategy = 'find';

    #[Groups(['pricing_rule_set:read', 'pricing_rule_set:write'])]
    protected array $options = [];

    public function __construct()
    {
        $this->rules = new ArrayCollection();
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return ArrayCollection<int,PricingRule>
     */
    public function getRules()
    {
        return $this->rules;
    }

    public function setRules($rules)
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * @param mixed $strategy
     *
     * @return self
     */
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function duplicate(TranslatorInterface $translator)
    {
        $ruleSet = new self();

        $translatedName = $translator->trans('adminDashboard.pricing.copyOf', [
            '%rule_set_name%' => $this->getName(),
        ]);

        $ruleSet->setName($translatedName);

        $rules = new ArrayCollection();
        foreach ($this->getRules() as $rule) {
            // do not assign same rule reference
            $rules->add(clone $rule);
        }
        $ruleSet->setRules($rules);

        $ruleSet->setStrategy($this->getStrategy());

        return $ruleSet;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    public function hasOption(string $option)
    {
        return in_array($option, $this->options);
    }
}
