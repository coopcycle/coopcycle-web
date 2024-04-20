<?php

namespace AppBundle\Action\Incident;

use AppBundle\Entity\Delivery\FailureReason;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

class CreateIncident
{

    public function __construct(
        private EntityManagerInterface $em,
        private TranslatorInterface $translator,
        private TaskManager $taskManager
    )
    { }

    private function loadFailureReasonsConfig(): array
    {
        $path = realpath(__DIR__ . '/../../Resources/config/failure_reasons.yml');
        $parser = new YamlParser();
        return $parser->parseFile($path, Yaml::PARSE_CONSTANT)['failure_reasons']['default'];
    }

    public function findDescriptionByCode(string $code): ?string
    {
        $defaults = $this->loadFailureReasonsConfig();
        $defaults = array_reduce($defaults, function($carry, $failure_reason) {
            $carry[$failure_reason['code']] = $failure_reason;
            return $carry;
        }, []);

        if (in_array($code, array_keys($defaults))) {
            return $this->translator->trans($defaults[$code]['description']);
        }

        $failure_reason = $this->em->getRepository(FailureReason::class)->findOneBy(['code' => $code]);
        if (!is_null($failure_reason)) {
            return $failure_reason->getDescription();
        }

        return null;
    }

    public function __invoke(Incident $data, UserInterface $user, Request $request): Incident
    {

        if (is_null($data->getTitle())) {
            $data->setTitle($this->findDescriptionByCode($data->getFailureReasonCode()));
        }

        $data->setCreatedBy($user);
        $this->em->persist($data);
        $this->em->flush();

        $this->taskManager->incident(
            $data->getTask(),
            $data->getFailureReasonCode(),
            $data->getDescription(),
            [
                'incident_id' => $data->getId()
            ]
        );

        return $data;
    }

}
