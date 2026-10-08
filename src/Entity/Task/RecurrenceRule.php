<?php

namespace AppBundle\Entity\Task;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use AppBundle\Action\Task\RecurrenceRuleBetween as BetweenController;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Store;
use AppBundle\Validator\Constraints\RecurrenceRuleTemplate as AssertRecurrenceRuleTemplate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\SoftDeleteable\SoftDeleteable as SoftDeleteableInterface;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Gedmo\Timestampable\Traits\Timestampable;
use Recurr\Rule;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    shortName: 'RecurrenceRule',
    operations: [
        new Get(
            // Make sure to add requirements, so "/recurrence_rules/generate_orders"
            // (declared on RecurrenceRuleGeneration) is not swallowed by this route
            requirements: ['id' => '[0-9]+'],
        ),
        new Put(),
        new Post(),
        new Delete(),
        new GetCollection(),
        new Post(
            uriTemplate: '/recurrence_rules/{id}/between',
            controller: BetweenController::class,
            write: false
        ),
    ],
    normalizationContext: ['groups' => ['task_recurrence_rule']],
    security: "is_granted('ROLE_DISPATCHER')"
)]
class RecurrenceRule implements SoftDeleteableInterface
{
    use SoftDeleteable;
    use Timestampable;

    /**
     * @var int
     */
    private $id;


    /**
     * @var string|null
     */
    #[Groups(['task_recurrence_rule'])]
    private $name;

    /**
     * @var Rule
     */
    #[ApiProperty(openapiContext: ['type' => 'string', 'example' => 'FREQ=WEEKLY'])]
    #[Groups(['task_recurrence_rule'])]
    private $rule;

    /**
     * @var array
     */
    #[Groups(['task_recurrence_rule'])]
    #[AssertRecurrenceRuleTemplate]
    private $template = [];

    #[Groups(['task_recurrence_rule'])]
    private ?array $arbitraryPriceTemplate = null;

    /**
     * The rule set chosen by a dispatcher to price the generated orders,
     * the store's one is used when null
     */
    #[Groups(['task_recurrence_rule'])]
    private ?PricingRuleSet $pricingRuleSet = null;

    /**
     * The manual supplements added to the generated orders
     *
     * @var Collection<int, RecurrenceRuleManualSupplement>
     */
    #[Assert\Valid]
    #[Groups(['task_recurrence_rule'])]
    private Collection $manualSupplements;

    /**
     * @var Store
     */
    #[Assert\NotNull]
    #[Groups(['task_recurrence_rule'])]
    private $store;

    private bool $generateOrders = false;

    #[Groups(['task_recurrence_rule'])]
    private bool $paused = false;

    public function __construct()
    {
        $this->manualSupplements = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the name of the object.
     *
     * @param string|null $name The name to set.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Retrieves the name associated with the object.
     *
     * @return string|null The name of the object.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Rule
     */
    public function getRule()
    {
        return $this->rule;
    }

    /**
     * @return self
     */
    public function setRule(Rule $rule)
    {
        $this->rule = $rule;

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @return self
     */
    public function setTemplate(array $template)
    {
        $this->template = $template;

        return $this;
    }

    public function getStore(): Store
    {
        return $this->store;
    }

    /**
     * @return self
     */
    public function setStore(Store $store)
    {
        $this->store = $store;

        return $this;
    }

    #[SerializedName('orgName')]
    #[Groups(['task_recurrence_rule'])]
    public function getOrganizationName()
    {
        return $this->store->getOrganization()->getName();
    }

    public function getArbitraryPriceTemplate(): ?array
    {
        return $this->arbitraryPriceTemplate;
    }

    public function setArbitraryPriceTemplate(?array $arbitraryPriceTemplate): void
    {
        $this->arbitraryPriceTemplate = $arbitraryPriceTemplate;
    }

    public function getPricingRuleSet(): ?PricingRuleSet
    {
        return $this->pricingRuleSet;
    }

    public function setPricingRuleSet(?PricingRuleSet $pricingRuleSet): void
    {
        $this->pricingRuleSet = $pricingRuleSet;
    }

    /**
     * @return Collection<int, RecurrenceRuleManualSupplement>
     */
    public function getManualSupplements(): Collection
    {
        return $this->manualSupplements;
    }

    public function addManualSupplement(RecurrenceRuleManualSupplement $manualSupplement): void
    {
        if (!$this->manualSupplements->contains($manualSupplement)) {
            $manualSupplement->setRecurrenceRule($this);
            $this->manualSupplements->add($manualSupplement);
        }
    }

    public function removeManualSupplement(RecurrenceRuleManualSupplement $manualSupplement): void
    {
        $this->manualSupplements->removeElement($manualSupplement);
    }

    /**
     * Only manual supplements of the rule set used to price the generated orders
     */
    #[Assert\Callback]
    public function validateManualSupplements(ExecutionContextInterface $context): void
    {
        // Not set yet when the store is missing, which is reported by its own constraint
        /** @var Store|null $store */
        $store = $this->store;

        $pricingRuleSet = $this->pricingRuleSet ?? $store?->getPricingRuleSet();

        foreach ($this->manualSupplements as $index => $manualSupplement) {
            $pricingRule = $manualSupplement->getPricingRule();
            if (is_null($pricingRule)) {
                continue;
            }

            $isApplicable = $pricingRule->isManualSupplement()
                && !is_null($pricingRuleSet)
                && $pricingRuleSet->getRules()->contains($pricingRule);

            if (!$isApplicable) {
                $context->buildViolation(sprintf('Pricing rule #%d is not a manual supplement of the pricing rule set used', $pricingRule->getId()))
                    ->atPath(sprintf('manualSupplements[%d].pricingRule', $index))
                    ->addViolation();
            }
        }
    }

    #[SerializedName('isCancelled')]
    #[Groups(['task_recurrence_rule'])]
    public function isCancelled(): bool
    {
        return $this->isDeleted();
    }

    public function isGenerateOrders(): bool
    {
        return $this->generateOrders;
    }

    public function setGenerateOrders(bool $generateOrders): void
    {
        $this->generateOrders = $generateOrders;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function setPaused(bool $paused): void
    {
        $this->paused = $paused;
    }

}
