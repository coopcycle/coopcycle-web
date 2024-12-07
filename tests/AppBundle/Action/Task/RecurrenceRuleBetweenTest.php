<?php

namespace Tests\AppBundle\Action\Task;

use AppBundle\Action\Task\RecurrenceRuleBetween;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Organization;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Pricing\PricingManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
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
        $this->pricingManager = $this->prophesize(PricingManager::class);

        $this->action = new RecurrenceRuleBetween(
            $this->denormalizer->reveal(),
            $this->entityManager->reveal(),
            $this->pricingManager->reveal()
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

    public function createTasksProvider()
    {
        return [
            [
                [
                    "after" => "2021-02-12T00:00:00+01:00",
                    "before" => "2021-02-12T23:59:59+01:00"
                ],
                "00:00",
                "23:59",
                [
                    '@type' => 'Task',
                    'type' => 'PICKUP',
                    'address' => [
                        'streetAddress' => '78 Avenue Victoria, 75001 Paris, France',
                    ],
                    'after' => (new \DateTime('2021-02-12 00:00:00'))->format(\DateTime::ATOM),
                    'before' => (new \DateTime('2021-02-12 23:59:00'))->format(\DateTime::ATOM)
                ]
            ],
            [
                [
                    "after" => "2021-02-12T00:00:00+01:00",
                    "before" => "2021-02-12T23:59:59+01:00"
                ],
                "10:00",
                "11:00",
                [
                    '@type' => 'Task',
                    'type' => 'PICKUP',
                    'address' => [
                        'streetAddress' => '78 Avenue Victoria, 75001 Paris, France',
                    ],
                    'after' => (new \DateTime('2021-02-12 10:00:00'))->format(\DateTime::ATOM),
                    'before' => (new \DateTime('2021-02-12 11:00:00'))->format(\DateTime::ATOM)
                ]
            ],
        ];
    }

    /**
     * @dataProvider createTasksProvider
     */
    public function testCreateTasks($requestBody, $after, $before, $expectedPayload)
    {
        $content = json_encode($requestBody);
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
                        "streetAddress" => "78 Avenue Victoria, 75001 Paris, France"
                    ],
                    "after" => $after,
                    "before" => $before
                ]
            ]
        ]);

        $expectedTask = new Task();

        $this->denormalizer->denormalize($expectedPayload, Task::class, 'jsonld', Argument::type('array'))
            ->willReturn($expectedTask)
            ->shouldBeCalled();

        $response = call_user_func_array($this->action, [$recurrenceRule, $request]);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);
        $this->assertContains($expectedTask, $response);
        $this->assertSame($organization, $expectedTask->getOrganization());
    }

    public function testCreateTasksWithOrder()
    {
        $content = json_encode([
            "after" => "2021-02-12T00:00:00+01:00",
            "before" => "2021-02-12T23:59:59+01:00"
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

        $pickupPayload = [
            "@type" => "Task",
            "type" => "PICKUP",
            "address" => [
                "streetAddress" => "78 Avenue Victoria, 75001 Paris, France"
            ],
            "after" => '10:00',
            "before" => '11:00'
        ];
        $dropoffPayload = [
            "@type" => "Task",
            "type" => "DROPOFF",
            "address" => [
                "streetAddress" => "12 Avenue Victoria, 75001 Paris, France"
            ],
            "after" => '11:00',
            "before" => '12:00'
        ];

        $recurrenceRule->setTemplate([
            '@type' => 'hydra:Collection',
            'hydra:member' => [
                $pickupPayload,
                $dropoffPayload
            ]
        ]);

        $pickupTask = new Task();
        $pickupTask->setType(Task::TYPE_PICKUP);

        $pickupPayloadWithTime = $pickupPayload;
        $pickupPayloadWithTime['after'] = (new \DateTime('2021-02-12 10:00:00'))->format(\DateTime::ATOM);
        $pickupPayloadWithTime['before'] = (new \DateTime('2021-02-12 11:00:00'))->format(\DateTime::ATOM);

        $this->denormalizer->denormalize($pickupPayloadWithTime, Task::class, 'jsonld', Argument::type('array'))
            ->willReturn($pickupTask)
            ->shouldBeCalled();

        $dropoffTask = new Task();

        $dropoffPayloadWithTime = $dropoffPayload;
        $dropoffPayloadWithTime['after'] = (new \DateTime('2021-02-12 11:00:00'))->format(\DateTime::ATOM);
        $dropoffPayloadWithTime['before'] = (new \DateTime('2021-02-12 12:00:00'))->format(\DateTime::ATOM);

        $this->denormalizer->denormalize($dropoffPayloadWithTime, Task::class, 'jsonld', Argument::type('array'))
            ->willReturn($dropoffTask)
            ->shouldBeCalled();

        $response = call_user_func_array($this->action, [$recurrenceRule, $request]);

        $this->assertIsArray($response);
        $this->assertNotEmpty($response);

        $this->assertContains($pickupTask, $response);
        $this->assertSame($organization, $pickupTask->getOrganization());

        $this->assertContains($dropoffTask, $response);
        $this->assertSame($organization, $dropoffTask->getOrganization());

        $this->pricingManager->createOrder(Argument::type(Delivery::class))->shouldHaveBeenCalled();
    }
}
