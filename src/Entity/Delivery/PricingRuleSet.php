<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Action\NotFoundAction;
use AppBundle\Action\PricingRuleSet\Applications;
use AppBundle\Api\State\ValidationAwareRemoveProcessor;
use AppBundle\Validator\Constraints\PricingRuleSetDelete as AssertCanDelete;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

#[ApiResource(
    operations: [
        new Get(controller: NotFoundAction::class),
        new Delete(
            security: 'is_granted(\'ROLE_ADMIN\')',
            validationContext: ['groups' => ['deleteValidation']],
            processor: ValidationAwareRemoveProcessor::class,
        ),
        new Get(
            uriTemplate: '/pricing_rule_sets/{id}/applications',
            controller: Applications::class,
            security: 'is_granted(\'ROLE_ADMIN\')',
            openapiContext: ['summary' => 'Get the objects to which this pricing rule set is applied']
        ),
        new Post(),
        new GetCollection()
    ]
)]
#[AssertCanDelete(groups: ['deleteValidation'])]
class PricingRuleSet
{
    /**
     * @var int
     */
    protected $id;

    #[Assert\Valid]
    protected $rules;

    protected $name;

    protected $strategy = 'find';

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
