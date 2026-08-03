<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\EmployeeProfile;
use AppBundle\Entity\EmployeeProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class EmployeeProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly EntityManagerInterface $entityManager)
    {}

    /**
     * @param EmployeeProfile $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof Post) {
            /** @var EmployeeProfileRepository $repository */
            $repository = $this->entityManager->getRepository(EmployeeProfile::class);

            if (null !== $repository->findOneByUser($data->getUser())) {
                throw new UnprocessableEntityHttpException('An employee profile already exists for this user.');
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
