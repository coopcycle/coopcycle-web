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
 *       "security"="is_granted('ROLE_ADMIN')"
 *     },
 *     "post"={
 *       "method"="POST",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     }
 *   },
 *   itemOperations={
 *     "get"={
 *       "method"="GET",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     },
 *     "put"={
 *       "method"="PUT",
 *       "security"="is_granted('ROLE_ADMIN')"
 *     },
 *     "between"={
 *       "method"="POST",
 *       "path"="/recurrence_rules/{id}/between",
 *       "security"="is_granted('ROLE_ADMIN')",
 *       "controller"=BetweenController::class
 *     },
 *     "delete"={
 *       "method"="DELETE",
 *       "security"="is_granted('ROLE_ADMIN')"
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
