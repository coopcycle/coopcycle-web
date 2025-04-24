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
use AppBundle\Action\Task\GenerateOrders;
use AppBundle\Entity\Store;
use AppBundle\Validator\Constraints\RecurrenceRuleTemplate as AssertRecurrenceRuleTemplate;
use Gedmo\SoftDeleteable\SoftDeleteable as SoftDeleteableInterface;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Gedmo\Timestampable\Traits\Timestampable;
use Recurr\Rule;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_DISPATCHER\')'),
        new Put(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_DISPATCHER\')'),
        new Post(
            uriTemplate: '/recurrence_rules/{id}/between',
            security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_DISPATCHER\')',
            controller: BetweenController::class,
            write: false
        ),
        new Delete(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_DISPATCHER\')'),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_DISPATCHER\')'),
        new Post(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_DISPATCHER\')'),
        new Post(
            uriTemplate: '/recurrence_rules/generate_orders',
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            controller: GenerateOrders::class
        )
    ],
    shortName: 'RecurrenceRule',
    normalizationContext: ['groups' => ['task_recurrence_rule']]
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
