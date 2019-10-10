<?php

namespace Tests\AppBundle\Functional;

use AppBundle\Controller\AdminController;
use AppBundle\Entity\Address;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ImageDownloadTest extends TestCase
{
    public function testGetImageDownloadFileName()
    {
        $date = new \DateTime();
        $dateFormatted = $date->format('Y-m-d');

        $method = new ReflectionMethod(AdminController::class, 'getImageDownloadFileName');
        $method->setAccessible(true);

        $task = new Task();
        $task->setCreatedAt($date);

        $taskImage = new TaskImage();
        $taskImage->setTask($task);
        $taskImage->setId(122);
        $taskImage->setImageName('122.png');

        $taskAddress = new Address();
        $taskAddress->setName('Test Name');

        $task->setAddress($taskAddress);

        $adminController = new AdminController();
        $adminController->setSlugify(new Slugify());

        $this->assertEquals(
            sprintf('122_test-name_%s.png', $dateFormatted),
            $method->invoke($adminController, $task, $taskImage)
        );
    }
}
