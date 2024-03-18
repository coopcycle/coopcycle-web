<?php

namespace AppBundle\Entity\Task;

use AppBundle\Action\Task\RecurrenceRuleBetween as BetweenController;
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

/**
 * @ApiResource(
 *   shortName="RecurrenceRule",
 *   normalizationContext={"groups"={"task_recurrence_rule"}},
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"
 *     },
 *     "post"={
 *       "method"="POST",
 *       "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"
 *     },
 *     "between"={
 *       "method"="POST",
 *       "path"="/recurrence_rules/{id}/between",
 *       "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')",
 *       "controller"=BetweenController::class
 *     },
 *     "delete"={
 *       "method"="DELETE",
 *       "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_DISPATCHER')"
 *     }
 *   }
 * )
 */
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
     * @Groups({"task_recurrence_rule"})
     */
    private $name;

    /**
     * @var Rule
     * @Groups({"task_recurrence_rule"})
     * @ApiProperty(
     *     attributes={
     *         "openapi_context"={
     *             "type"="string",
     *             "example"="FREQ=WEEKLY"
     *         }
     *     }
     * )
     */
    private $rule;

    /**
     * @var array
     * @Groups({"task_recurrence_rule"})
     * @AssertRecurrenceRuleTemplate
     */
    private $template = [];

    /**
     * @var Store
     * @Assert\NotNull
     * @Groups({"task_recurrence_rule"})
     */
    private $store;

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
     * @param Rule $rule
     *
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
     * @param array $template
     *
     * @return self
     */
    public function setTemplate(array $template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return Store
     */
    public function getStore(): Store
    {
        return $this->store;
    }

    /**
     * @param Store $store
     *
     * @return self
     */
    public function setStore(Store $store)
    {
        $this->store = $store;

        return $this;
    }

    /**
     * @SerializedName("orgName")
     * @Groups({"task_recurrence_rule"})
     */
    public function getOrganizationName()
    {
        return $this->store->getOrganization()->getName();
    }
}
