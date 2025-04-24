<?php

namespace AppBundle\Entity\Delivery;

use ApiPlatform\Core\Action\NotFoundAction;
use ApiPlatform\Core\Annotation\ApiResource;
use AppBundle\Action\PricingRuleSet\Applications;
use AppBundle\Validator\Constraints\PricingRuleSetDelete as AssertCanDelete;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

#[ApiResource(
    itemOperations: [
        'get' => [
            'controller' => NotFoundAction::class
        ],
        'delete' => [
            'method' => 'DELETE',
            'security' => "is_granted('ROLE_ADMIN')",
            'validation_groups' => ['deleteValidation']
        ],
        'applications' => [
            'method' => 'GET',
            'path' => '/pricing_rule_sets/{id}/applications',
            'controller' => Applications::class,
            'security' => "is_granted('ROLE_ADMIN')",
            'openapi_context' => ['summary' => 'Get the objects to which this pricing rule set is applied']
        ]
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
