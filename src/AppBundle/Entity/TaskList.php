<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\Task\CollectionTrait as TaskCollectionTrait;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity
 * @ORM\Table(name="task_list", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="task_list_unique", columns={"date", "courier_id"})}
 * )
 */
class TaskList extends TaskCollection implements TaskCollectionInterface
{
    use TaskCollectionTrait;

    /**
     * @ORM\Column(type="date")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="ApiUser")
     * @ORM\JoinColumn(nullable=false)
     */
    private $courier;

    /**
     * @ORM\Column(type="text")
     */
    private $polyline;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     * @Groups({"task"})
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     * @Groups({"task"})
     */
    private $updatedAt;

    public function getId()
    {
        return $this->id;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate(\DateTime $date)
    {
        $this->date = $date;

        return $this;
    }

    public function getCourier()
    {
        return $this->courier;
    }

    public function setCourier($courier)
    {
        $this->courier = $courier;

        return $this;
    }

    public function getPolyline()
    {
        return $this->polyline;
    }

    public function setPolyline($polyline)
    {
        $this->polyline = $polyline;

        return $this;
    }
}
