<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\HolidayRequest;
use AppBundle\Service\ShiftManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class HolidayRequestStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
        private readonly ShiftManager $shiftManager,
        private readonly TranslatorInterface $translator)
    {}

    /**
     * @param HolidayRequest $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data->isPending()) {
            throw new BadRequestHttpException(
                sprintf('Holiday request #%d has already been %s', $data->getId(), $data->getStatus())
            );
        }

        $status = str_contains($operation->getUriTemplate(), 'approve') ?
            HolidayRequest::STATUS_APPROVED : HolidayRequest::STATUS_REJECTED;

        $data->setStatus($status);
        $data->setActionedBy($this->security->getUser());
        $data->setActionedAt(new \DateTime());

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->shiftManager->notify(
            $this->translator->trans(sprintf('notifications.holiday_request.%s', $status), [
                '%start%' => $data->getStartDate()->format('Y-m-d'),
                '%end%' => $data->getEndDate()->format('Y-m-d'),
            ]),
            [$data->getUser()]
        );

        return $result;
    }
}
