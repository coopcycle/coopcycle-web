<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Entity\Shift;
use AppBundle\Entity\User;
use AppBundle\Service\ShiftApplicationManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ShiftApplicationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ShiftApplicationManager $shiftApplicationManager,
        private readonly Security $security)
    {}

    /**
     * @param Shift $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // This processor is only ever wired to the /apply and /unapply HTTP
        // PUT operations, both of which are HttpOperation instances
        if (!$operation instanceof HttpOperation) {
            throw new \LogicException(sprintf('Expected an instance of %s.', HttpOperation::class));
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new BadRequestHttpException('No authenticated user');
        }

        // Careful: "unapply" contains "apply"
        if (str_contains($operation->getUriTemplate() ?? '', 'unapply')) {
            $this->shiftApplicationManager->unapply($data, $user);
        } else {
            $this->shiftApplicationManager->apply($data, $user);
        }

        return $data;
    }
}
