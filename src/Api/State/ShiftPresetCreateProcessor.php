<?php

namespace AppBundle\Api\State;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftPresetCreateInput;
use AppBundle\Entity\Skill;
use AppBundle\Entity\ShiftActivity;
use AppBundle\Entity\ShiftPreset;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ShiftPresetCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly IriConverterInterface $iriConverter,
        private readonly Security $security)
    {}

    /**
     * @param ShiftPresetCreateInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftPreset
    {
        $activity = $this->entityManager->getRepository(ShiftActivity::class)
            ->findOneBySlug($data->activity);
        if (null === $activity) {
            throw new BadRequestHttpException(sprintf('Unknown shift activity "%s"', $data->activity));
        }

        $startTime = \DateTime::createFromFormat('H:i', $data->startTime);
        $endTime = \DateTime::createFromFormat('H:i', $data->endTime);
        if (false === $startTime || false === $endTime) {
            throw new BadRequestHttpException('Invalid startTime/endTime, expected "HH:MM"');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $preset = new ShiftPreset();
        $preset->setName($data->name);
        $preset->setActivity($data->activity);
        $preset->setStartTime($startTime);
        $preset->setEndTime($endTime);
        $preset->setSlots($data->slots);
        $preset->setBreakMinutes($data->breakMinutes);
        $preset->setComment($data->comment);
        $preset->setCreatedBy($user);

        foreach ($data->requiredSkills as $iri) {
            /** @var Skill $skill */
            $skill = $this->iriConverter->getResourceFromIri($iri);
            $preset->addRequiredSkill($skill);
        }

        $this->entityManager->persist($preset);
        $this->entityManager->flush();

        return $preset;
    }
}
