<?php

namespace AppBundle\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use AppBundle\Api\Dto\SearchQueryInput;
use AppBundle\Api\State\MySearchQueriesProvider;
use AppBundle\Api\State\SearchQueryCreateProcessor;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A search bar query saved by a user for later reuse - e.g. the
 * /admin/orders search bar (see js/app/components/SearchQueryBar).
 * "scope" identifies which search bar a saved query belongs to, since the
 * same mechanism is meant to back multiple search bars in different
 * contexts (only "orders" for now).
 *
 * Not to be confused with the unrelated AppBundle\SearchQuery\* namespace
 * (src/SearchQuery/), which holds the server-side query-building/autocomplete
 * logic for the search bars themselves.
 */
#[ApiResource(
    shortName: 'SearchQuery',
    operations: [
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\')'),
        new Post(
            input: SearchQueryInput::class,
            processor: SearchQueryCreateProcessor::class,
            security: 'is_granted(\'ROLE_USER\')'
        ),
        new Get(security: 'is_granted(\'ROLE_ADMIN\') or object.getUser() == user'),
        new Delete(security: 'is_granted(\'ROLE_ADMIN\') or object.getUser() == user'),
        new GetCollection(
            uriTemplate: '/me/search_queries',
            paginationEnabled: false,
            provider: MySearchQueriesProvider::class,
            security: 'is_granted(\'ROLE_USER\')'
        ),
    ],
    normalizationContext: ['groups' => ['search_query']],
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['user' => 'exact', 'scope' => 'exact'])]
class SearchQuery
{
    #[Groups(['search_query'])]
    protected $id;

    #[Groups(['search_query'])]
    protected ?UserInterface $user = null;

    #[Groups(['search_query'])]
    protected string $scope;

    #[Groups(['search_query'])]
    protected string $query;

    #[Groups(['search_query'])]
    protected string $name;

    protected $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): self
    {
        $this->query = $query;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    #[Groups(['search_query'])]
    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
