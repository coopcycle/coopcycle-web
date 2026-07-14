<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\ShiftCompliance;
use AppBundle\Service\Shift\Compliance\ComplianceChecker;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class ShiftComplianceProvider implements ProviderInterface
{
    public function __construct(
        private readonly ComplianceChecker $checker)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShiftCompliance
    {
        $week = $context['filters']['week'] ?? 'now';

        try {
            $monday = (new \DateTimeImmutable($week))->modify('monday this week');
        } catch (\Exception $e) {
            throw new BadRequestException('Invalid week parameter');
        }

        $result = $this->checker->check($monday);

        return new ShiftCompliance(
            $monday->format('Y-m-d'),
            $result['template'],
            $result['violations']
        );
    }
}
