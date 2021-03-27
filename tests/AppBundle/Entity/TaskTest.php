<?php

namespace Tests\AppBundle\Entity;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\User;
use AppBundle\Entity\TaskEvent;
use AppBundle\Validator\Constraints\Task as TaskConstraint;
use AppBundle\Validator\Constraints\TaskValidator;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ValidatorBuilder;

class TaskTest extends TestCase
{
    use ProphecyTrait;

    private $task;
    private $courier;

    protected function setUp(): void
    {
        $this->task = new Task();
        $this->courier = new User();
    }

    public function testSetPrevious()
    {
        $previoustask = new Task();
        $this->task->setPrevious($previoustask);
        $this->assertSame($previoustask, $this->task->getPrevious());
    }

    public function testHasPrevious()
    {
        $this->assertFalse($this->task->hasPrevious());
        $previoustask = new Task();
        $this->task->setPrevious($previoustask);
        $this->assertTrue($this->task->hasPrevious());
    }

    public function testAssignTo()
    {
        $this->task->assignTo($this->courier);
        $this->assertTrue($this->task->isAssigned());
        $this->assertTrue($this->task->isAssignedTo($this->courier));
        $this->assertEquals($this->task->getAssignedCourier(), $this->courier);
    }

    public function testUnassign()
    {
        $this->task->assignTo($this->courier);
        $this->task->unassign();
        $this->assertNull($this->task->getAssignedCourier());
    }

    public function testHasEvent()
    {
        $event = new TaskEvent($this->task, "PICKUP");
        $this->task->addEvent($event);

        $this->assertTrue($this->task->hasEvent("PICKUP"));
        $this->assertFalse($this->task->hasEvent("DROPOFF"));
    }

    private function createTaskEvent($name, \DateTime $createdAt)
    {
        $taskEvent = new TaskEvent($this->task, $name);

        $reflection = new \ReflectionObject($taskEvent);
        $property = $reflection->getProperty('createdAt');
        $property->setAccessible(true);
        $property->setValue($taskEvent, $createdAt);

        return $taskEvent;
    }

    public function testGetLastEvent()
    {
        $event1 = $this->createTaskEvent('task:assigned', new \DateTime('2018-04-11 12:00:00'));
        $event2 = $this->createTaskEvent('task:unassigned', new \DateTime('2018-04-11 13:00:00'));
        $event3 = $this->createTaskEvent('task:assigned', new \DateTime('2018-04-11 14:00:00'));
        $event4 = $this->createTaskEvent('task:unassigned', new \DateTime('2018-04-11 15:00:00'));

        $this->task->addEvent($event1);
        $this->task->addEvent($event2);
        $this->task->addEvent($event3);
        $this->task->addEvent($event4);

        $this->assertSame($this->task->getLastEvent('task:assigned'), $event3);
    }

    public function testDuplicate()
    {
        $address = new Address();

        $task = new Task();
        $task->setType('DROPOFF');
        $task->setAddress($address);
        $task->setComments('Beware of the dog');
        $task->setDoneAfter(new \DateTime('2019-08-18 08:00'));
        $task->setDoneBefore(new \DateTime('2019-08-18 12:00'));

        $clone = $task->duplicate();

        $this->assertEquals('DROPOFF', $clone->getType());
        $this->assertSame($address, $clone->getAddress());
        $this->assertEquals('Beware of the dog', $clone->getComments());
        $this->assertEquals(new \DateTime('2019-08-18 08:00'), $clone->getDoneAfter());
        $this->assertEquals(new \DateTime('2019-08-18 12:00'), $clone->getDoneBefore());
    }

    public function testDuplicateFailedTask()
    {
        $address = new Address();

        $task = new Task();
        $task->setType('DROPOFF');
        $task->setStatus('FAILED');
        $task->setAddress($address);
        $task->setComments('Beware of the dog');
        $task->setDoneAfter(new \DateTime('2019-08-18 08:00'));
        $task->setDoneBefore(new \DateTime('2019-08-18 12:00'));

        $clone = $task->duplicate();

        $delivery = new Delivery();
        $delivery->removeTask($delivery->getDropoff());
        $delivery->addTask($task);

        $this->assertEquals('DROPOFF', $clone->getType());
        $this->assertEquals('TODO', $clone->getStatus());
        $this->assertSame($address, $clone->getAddress());
        $this->assertEquals('Beware of the dog', $clone->getComments());
        $this->assertEquals(new \DateTime('2019-08-18 08:00'), $clone->getDoneAfter());
        $this->assertEquals(new \DateTime('2019-08-18 12:00'), $clone->getDoneBefore());
    }

    public function testValidation()
    {
        $unitOfWork = $this->prophesize(UnitOfWork::class);
        $doctrine = $this->prophesize(ManagerRegistry::class);
        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $entityManager
            ->getUnitOfWork()
            ->willReturn($unitOfWork->reveal());

        $doctrine
            ->getManagerForClass(Task::class)
            ->willReturn($entityManager->reveal());

        $realFactory = new ConstraintValidatorFactory();

        $uniqueEntityValidator = $this->prophesize(UniqueEntityValidator::class);

        $factory = $this->prophesize(ConstraintValidatorFactoryInterface::class);
        $factory
            ->getInstance(Argument::type(Constraint::class))
            ->will(function ($args) use ($doctrine, $realFactory, $uniqueEntityValidator) {

                if ($args[0] instanceof TaskConstraint) {
                    return new TaskValidator($doctrine->reveal());
                }

                // We return a mock of UniqueEntityValidator, so this is not tested
                if ($args[0] instanceof UniqueEntity) {
                    return $uniqueEntityValidator->reveal();
                }

                return $realFactory->getInstance($args[0]);
            });

        $validatorBuilder = new ValidatorBuilder();
        $validator = $validatorBuilder
            ->enableAnnotationMapping()
            ->setConstraintValidatorFactory($factory->reveal())
            ->getValidator();

        $task = new Task();
        $task->setDoneAfter(new \DateTime('2019-08-18 12:00'));
        $task->setDoneBefore(new \DateTime('2019-08-18 08:00'));

        $violations = $validator->validate($task);
        $this->assertCount(2, $violations);
        $this->assertSame('address', $violations->get(0)->getPropertyPath());
        $this->assertSame('doneBefore', $violations->get(1)->getPropertyPath());

        $address = new Address();
        $address->setStreetAddress('1, Rue de Rivoli Paris');
        $address->setGeo(new GeoCoordinates(48.856613, 2.352222));

        $task = new Task();
        $task->setAddress($address);
        $task->setDoneAfter(new \DateTime('2019-08-18 12:00'));
        $task->setDoneBefore(new \DateTime('2019-08-18 12:00'));

        $violations = $validator->validate($task);
        $this->assertCount(1, $violations);
        $this->assertSame('doneBefore', $violations->get(0)->getPropertyPath());

        $task = new Task();
        $task->setAddress($address);
        $task->setDoneAfter(null);
        $task->setDoneBefore(new \DateTime('2019-08-18 08:00'));

        $violations = $validator->validate($task);
        $this->assertCount(0, $violations);

        $task = new Task();
        $task->setAddress($address);
        $task->setDoneAfter(new \DateTime('2019-08-18 08:00'));
        $task->setDoneBefore(new \DateTime('2019-08-18 12:00'));

        $violations = $validator->validate($task);
        $this->assertCount(0, $violations);
    }
}
