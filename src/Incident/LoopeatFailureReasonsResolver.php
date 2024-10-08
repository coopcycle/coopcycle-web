<?php

namespace AppBundle\Incident;

use AppBundle\Entity\Task;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoopeatFailureReasonsResolver implements FailureReasonsResolverInterface
{
    public function __construct(private TranslatorInterface $translator)
    {}

    public function supports(Task $task): bool
    {
        if ($task->isDropoff()) {
            if (null !== $delivery = $task->getDelivery()) {
                if (null !== $order = $delivery->getOrder()) {

                    return $order->hasLoopeatReturns();
                }
            }
        }

        return false;
    }

    public function getFailureReasons(Task $task): array
    {
        $order = $task->getDelivery()->getOrder();

        $metadata = [];
        foreach ($order->getLoopeatReturns() as $i => $return) {
            $format = $order->getLoopeatFormatById($return['format_id']);
            if ($format) {
                $metadata[] = [
                    'type' => 'hidden',
                    'name' => "loopeat_returns[{$i}][format_id]",
                    'value' => $return['format_id'],
                ];
                $metadata[] = [
                    'type' => 'number',
                    'name' => "loopeat_returns[{$i}][quantity]",
                    'value' => $return['quantity'],
                    'label' => $format['title'],
                ];
            }
        }

        return [
            [
                'code' => 'zero_waste_unexpected_returns',
                'description' => $this->translator->trans('loopeat.incident.unexpected_returns'),
                'metadata' => $metadata,
            ]
        ];
    }
}
