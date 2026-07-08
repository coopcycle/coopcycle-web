<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Api\Resource\BankHolidays;
use AppBundle\Service\BankHolidayProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class BankHolidaysProvider implements ProviderInterface
{
    public function __construct(
        private readonly BankHolidayProvider $bankHolidayProvider,
        private readonly RequestStack $requestStack,
        private readonly string $locale)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BankHolidays
    {
        $request = $this->requestStack->getCurrentRequest();
        $params = $request?->query->all('date') ?? [];

        if (empty($params['after']) || empty($params['before'])) {
            throw new BadRequestHttpException('date[after] and date[before] are required');
        }

        $start = new \DateTime($params['after']);
        $end = new \DateTime($params['before']);

        return new BankHolidays(
            $this->bankHolidayProvider->getHolidaysBetween($start, $end, $this->locale)
        );
    }
}
