<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Shift;
use AppBundle\Service\ShiftManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class ShiftProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly ShiftManager $shiftManager,
        private readonly Security $security,
        private readonly TranslatorInterface $translator)
    {}

    /**
     * @param Shift $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (null === $data->getId()) {
            $data->setCreatedBy($this->security->getUser());
        }

        $added = [];

        $users = $data->getUsers();
        if (null !== $users) {
            $added = $this->shiftManager->syncAssignments($data, $users);
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if (count($added) > 0) {
            $this->shiftManager->notify(
                $this->translator->trans('notifications.shifts.assigned', [
                    '%date%' => $data->getStartsAt()->format('Y-m-d'),
                ]),
                $added
            );
        }

        return $result;
    }
}
