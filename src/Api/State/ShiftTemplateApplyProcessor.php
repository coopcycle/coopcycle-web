<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftTemplateApplyInput;
use AppBundle\Api\Resource\ShiftTemplateApplyResult;
use AppBundle\Entity\ShiftTemplate;
use AppBundle\Service\ShiftManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShiftTemplateApplyProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShiftManager $shiftManager)
    {}

    /**
     * @param ShiftTemplateApplyInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftTemplateApplyResult
    {
        $template = $this->entityManager->getRepository(ShiftTemplate::class)->find($uriVariables['id'] ?? 0);
        if (null === $template) {
            throw new NotFoundHttpException('Shift template not found');
        }

        $targetMonday = (new \DateTimeImmutable($data->targetWeek))->modify('monday this week');

        $created = $this->shiftManager->applyTemplate($template, $targetMonday, $data->includeAssignees);

        return new ShiftTemplateApplyResult(count($created));
    }
}
