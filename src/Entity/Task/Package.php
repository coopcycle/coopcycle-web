<?php

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Package\PackageWithQuantityInterface;
use AppBundle\Entity\Task;
use Symfony\Component\Serializer\Annotation\Groups;

class Package implements PackageWithQuantityInterface
{

    #[Groups(['package'])]
    protected $id;


    #[Groups(['package'])]
    protected $package;

    protected $task;


    #[Groups(['package'])]
    protected $quantity = 0;

    public function __construct(Task $task = null)
    {
        $this->task = $task;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    public function getPackage(): \AppBundle\Entity\Package
    {
        return $this->package;
    }

    /**
     * @param mixed $package
     *
     * @return self
     */
    public function setPackage($package)
    {
        $this->package = $package;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTask()
    {
        return $this->task;
    }

    /**
     * @param mixed $task
     *
     * @return self
     */
    public function setTask($task)
    {
        $this->task = $task;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param mixed $quantity
     *
     * @return self
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }
}
