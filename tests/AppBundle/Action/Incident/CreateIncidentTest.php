<?php

namespace Tests\AppBundle\Action\Incident;

use AppBundle\Action\Incident\CreateIncident;
use AppBundle\Entity\Delivery\FailureReasonRegistry;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Task;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

class CreateIncidentTest extends TestCase
{
    use ProphecyTrait;

    public function testFindDescriptionByCodeWithNullOrEmptyTitle()
    {
        $em = $this->prophesize(EntityManagerInterface::class);
        $taskManager = $this->prophesize(TaskManager::class);
        $failureReasonRegistry = $this->prophesize(FailureReasonRegistry::class);

        $failureReasonRegistry->getFailureReasons()->willReturn([]);

        $task = new Task();

        $incident = new Incident();
        $incident->setTask($task);
        $incident->setDescription('');
        $incident->setFailureReasonCode(null);

        $user = $this->prophesize(UserInterface::class)->reveal();
        $request = Request::create('/foo', content: '{}');

        $taskManager->incident($task, '', 'N/A', Argument::type('array'), $incident)->shouldBeCalled();

        $action = new CreateIncident($em->reveal(), $taskManager->reveal(), $failureReasonRegistry->reveal());

        $response = call_user_func_array($action, [$incident, $user, $request]);

        $this->assertEquals('N/A', $incident->getTitle());
    }
}

