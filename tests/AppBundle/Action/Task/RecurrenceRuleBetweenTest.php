<?php

namespace Tests\AppBundle\Action\Task;

use AppBundle\Action\Task\RecurrenceRuleBetween;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\RecurrenceRule;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Recurr\Rule;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class RecurrenceRuleBetweenTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->denormalizer = $this->prophesize(DenormalizerInterface::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);

        $this->action = new RecurrenceRuleBetween(
            $this->denormalizer->reveal(),
            $this->entityManager->reveal()
        );
    }

    public function testEmptyTemplate()
    {
        $request = Request::create('/api/recurrence_rules/1/between', 'POST');

        $recurrenceRule = new RecurrenceRule();

        $response = call_user_func_array($this->action, [$recurrenceRule, $request]);

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testCreateTasks()
    {
        $content = json_encode([
            "after" => "2021-02-12 00:00:00",
            "before" => "2021-02-12 23:59:59"
        ]);
        $request = Request::create('/api/recurrence_rules/1/between', 'POST', [], [], [], [], $content);

        $organization = new Organization();

        $store = new Store();
        $store->setOrganization($organization);

        $recurrenceRule = new RecurrenceRule();
        $recurrenceRule->setStore($store);
        $recurrenceRule->setRule(
            new Rule('FREQ=WEEKLY;BYDAY=MO,FR')
        );
        $recurrenceRule->setTemplate([
            '@type' => 'hydra:Collection',
            'hydra:member' => [
                [
                    "@type" => "Task",
                    "type" => "PICKUP",
                    "address" => [
                        "@id" => "/api/addresses/875",
                        "streetAddress" => "78 Avenue Victoria, 75001 Paris, France"
                    ],
                    "after" => "10:00",
                    "before" => "11:00"
                ]
            ]
        ]);

        $expectedPayload = [
            '@type' => 'Task',
            'type' => 'PICKUP',
            'address' => '/api/addresses/875',
            'after' => '2021-02-12T10:00:00+01:00',
            'before' => '2021-02-12T11:00:00+01:00'
        ];

        $expectedTask = new Task();

        $this->denormalizer->denormalize($expectedPayload, Task::class, 'jsonld')
            ->willReturn($expectedTask)
            ->shouldBeCalled();

        $response = call_user_func_array($this->action, [$recurrenceRule, $request]);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertContains($expectedTask, $response);
        $this->assertSame($organization, $expectedTask->getOrganization());
    }
}
