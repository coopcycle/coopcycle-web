<?php
declare(strict_types=1);

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Symfony\Component\Serializer\Annotation\Groups;


/**
 * @ApiResource(
 *   attributes={
 *     "normalization_context"={"groups"={"org"}},
 *   },
 *   collectionOperations={
 *     "get"={
 *       "method"="GET",
 *       "access_control"="is_granted('ROLE_DISPATCHER') or is_granted('ROLE_ADMIN')",
 *      },
 *   },
 *   order={"name": "ASC"},
 * )
 */
class Organization
{
    use SoftDeleteable;

    private $id;

    /**
    * @Groups({"org"})
    */
    private $name;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
}
