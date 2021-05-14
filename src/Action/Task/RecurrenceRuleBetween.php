<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class RecurrenceRuleBetween
{
    public function __construct(DenormalizerInterface $denormalizer, EntityManagerInterface $entityManager)
    {
        $this->denormalizer = $denormalizer;
        $this->entityManager = $entityManager;
    }

    public function __invoke($data, Request $request)
    {
        $template = $data->getTemplate();

        if (empty($template)) {
            return [];
        }

        $body = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        $payloads = $template['@type'] === 'hydra:Collection' ?
            $template['hydra:member'] : [ $template ];

        $transformer = new ArrayTransformer();
        $constraint = new BetweenConstraint(
            new \DateTime($body['after']),
            new \DateTime($body['before']),
            $inc = true
        );

        $startDate = new \DateTime($body['after']);

        $rule = $data->getRule();

        $payloads = array_map(function ($payload) use ($transformer, $constraint, $rule, $startDate) {

            $payload['address'] = $payload['address']['@id'];

            $rule->setStartDate(
                new \DateTime($startDate->format('Y-m-d') . ' ' . $payload['after'])
            );
            $rule->setEndDate(
                new \DateTime($startDate->format('Y-m-d') . ' ' . $payload['before'])
            );

            foreach ($transformer->transform($rule, $constraint) as $r) {
                $payload['after'] = $r->getStart()->format(\DateTime::ATOM);
                $payload['before'] = $r->getEnd()->format(\DateTime::ATOM);
            }

            return $payload;
        }, $payloads);

        $tasks = [];
        foreach ($payloads as $payload) {
            $task = $this->denormalizer->denormalize($payload, Task::class, 'jsonld');
            $task->setOrganization($data->getStore()->getOrganization());
            $task->setRecurrenceRule($data);
            $this->entityManager->persist($task);
            $tasks[] = $task;
        }

        $this->entityManager->flush();

        return $tasks;
    }
}
