<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GET /api/incidents is open to couriers, who must only see the incidents of
 * the tasks they are assigned to, plus the ones they reported themselves.
 * Dispatchers & admins see the whole instance.
 *
 * @see \AppBundle\Security\IncidentVoter for the item-level counterpart.
 */
class IncidentCollectionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private readonly Security $security)
    {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []): void
    {
        if (Incident::class !== $resourceClass) {
            return;
        }

        if (!$operation instanceof GetCollection) {
            return;
        }

        if ($this->security->isGranted('ROLE_DISPATCHER')) {
            return;
        }

        $user = $this->security->getUser();

        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (!$user instanceof User) {
            // Should not happen, the operation requires ROLE_COURIER;
            // deny rather than leak if it ever does.
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $taskAlias = $queryNameGenerator->generateJoinAlias('task');
        $parameterName = $queryNameGenerator->generateParameterName('user');

        $queryBuilder
            ->leftJoin(sprintf('%s.task', $rootAlias), $taskAlias)
            ->andWhere(sprintf('%s.assignedTo = :%s OR %s.createdBy = :%s',
                $taskAlias, $parameterName, $rootAlias, $parameterName))
            ->setParameter($parameterName, $user);
    }
}
