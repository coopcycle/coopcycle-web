<?php

namespace AppBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A competency (e.g. "cargo bike + trailer") that can be assigned to users
 * who are trained for it, and required by shifts that need it. Used to warn
 * (never block) when a shift is staffed with someone lacking a required skill.
 */
#[ApiResource(
    shortName: 'Skill',
    operations: [
        new GetCollection(
            paginationEnabled: false,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
        new Post(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new Get(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new Put(security: 'is_granted(\'ROLE_DISPATCHER\')'),
        new Delete(security: 'is_granted(\'ROLE_DISPATCHER\')'),
    ],
    normalizationContext: ['groups' => ['skill']],
    denormalizationContext: ['groups' => ['skill_write']]
)]
class Skill
{
    #[Groups(['skill', 'shift', 'user'])]
    protected $id;

    #[Groups(['skill', 'skill_write', 'shift', 'user'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected ?string $name = null;

    /**
     * Users trained for this skill. Owning side of the M2M — assigning
     * trainees is done by PUT-ing the skill with a `users` array of IRIs.
     *
     * @var Collection<int, UserInterface>
     */
    #[Groups(['skill', 'skill_write'])]
    protected Collection $users;

    protected $createdAt;

    protected $updatedAt;

    public function __construct()
    {
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, UserInterface>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(UserInterface $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    public function removeUser(UserInterface $user): self
    {
        $this->users->removeElement($user);

        return $this;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
