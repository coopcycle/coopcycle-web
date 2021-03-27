<?php

namespace AppBundle\Validator;

use AppBundle\Entity\Task;
use AppBundle\Validator\Constraints\Task as TaskConstraint;
use AppBundle\Validator\Constraints\TaskValidator;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\ValidatorBuilder;

class TaskValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    private $unitOfWork;
    private $doctrine;

    public function setUp() :void
    {
        $this->unitOfWork = $this->prophesize(UnitOfWork::class);
        $this->doctrine = $this->prophesize(ManagerRegistry::class);

        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $entityManager
            ->getUnitOfWork()
            ->willReturn($this->unitOfWork->reveal());

        $this->doctrine
            ->getManagerForClass(Task::class)
            ->willReturn($entityManager->reveal());

        parent::setUp();
    }

    protected function createValidator()
    {
        return new TaskValidator($this->doctrine->reveal());
    }

    public function testCannotChangeType()
    {
        $previousTask = new Task();
        $previousTask->setType(Task::TYPE_PICKUP);

        $task = new Task();
        $task->setType(Task::TYPE_PICKUP);
        $task->setPrevious($previousTask);

        $this->unitOfWork
            ->getOriginalEntityData($task)
            ->willReturn(['type' => Task::TYPE_DROPOFF]);

        $constraint = new TaskConstraint();
        $violations = $this->validator->validate($task, $constraint);

        $this->buildViolation($constraint->typeNotEditable)
            ->atPath('property.path.type')
            ->assertRaised();
    }

    public function testCanChangeType()
    {
        $task = new Task();
        $task->setType(Task::TYPE_PICKUP);

        $this->unitOfWork
            ->getOriginalEntityData($task)
            ->willReturn(['type' => Task::TYPE_DROPOFF]);

        $constraint = new TaskConstraint();
        $violations = $this->validator->validate($task, $constraint);

        $this->assertNoViolation();
    }

    public function testNoChanges()
    {
        $task = new Task();
        $task->setType(Task::TYPE_PICKUP);

        $this->unitOfWork
            ->getOriginalEntityData($task)
            ->willReturn(['type' => Task::TYPE_PICKUP]);

        $constraint = new TaskConstraint();
        $violations = $this->validator->validate($task, $constraint);

        $this->assertNoViolation();
    }
}
