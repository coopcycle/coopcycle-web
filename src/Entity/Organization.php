<?php
declare(strict_types=1);

namespace AppBundle\Entity;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Gedmo\SoftDeleteable\Traits\SoftDeleteable;
use Symfony\Component\Serializer\Annotation\Groups;


#[ApiResource(operations: [new Get(), new Put(), new Patch(), new Delete(), new GetCollection(security: 'is_granted(\'ROLE_DISPATCHER\') or is_granted(\'ROLE_ADMIN\')')], normalizationContext: ['groups' => ['org']], order: ['name' => 'ASC'])]
class Organization
{
    use SoftDeleteable;

    private $id;

    #[Groups(['org'])]
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
