<?php

namespace AppBundle\Entity\Task;

use AppBundle\Action\Task\RecurrenceRuleBetween as BetweenController;
use AppBundle\Action\Task\GenerateOrders;
use AppBundle\Entity\Store;
use AppBundle\Validator\Constraints\RecurrenceRuleTemplate as AssertRecurrenceRuleTemplate;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiProperty;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Gedmo\Timestampable\Traits\Timestampable;
use Recurr\Rule;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'RecurrenceRule', normalizationContext: ['groups' => ['task_recurrence_rule']], collectionOperations: ['get' => ['method' => 'GET', 'security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"], 'post' => ['method' => 'POST', 'security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"], 'generate_orders' => ['method' => 'POST', 'path' => '/recurrence_rules/generate_orders', 'security' => "is_granted('ROLE_DISPATCHER')", 'controller' => GenerateOrders::class]], itemOperations: ['get' => ['method' => 'GET', 'security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"], 'put' => ['method' => 'PUT', 'security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"], 'between' => ['method' => 'POST', 'path' => '/recurrence_rules/{id}/between', 'security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')", 'controller' => BetweenController::class], 'delete' => ['method' => 'DELETE', 'security' => "is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"]])]
class RecurrenceRule
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
    #[Groups(['task_recurrence_rule'])]
    #[ApiProperty(attributes: ['openapi_context' => ['type' => 'string', 'example' => 'FREQ=WEEKLY']])]
    private $rule;

    /**
     * @var array
     */
    #[Groups(['task_recurrence_rule'])]
    #[AssertRecurrenceRuleTemplate]
    private $template = [];

    private ?array $arbitraryPriceTemplate = null;

    /**
     * @var Store
     */
    #[Assert\NotNull]
    #[Groups(['task_recurrence_rule'])]
    private $store;

    private bool $generateOrders = false;

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

}
