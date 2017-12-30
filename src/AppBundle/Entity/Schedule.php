<?php

namespace AppBundle\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use FOS\UserBundle\Model\User as BaseUser;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(shortName="Schedule", attributes={
 *     "denormalization_context"={"groups"={"schedule"}},
 *     "normalization_context"={"groups"={"schedule", "schedule_item"}}
 * }, collectionOperations={
 *     "get"={"method"="get"}
 * }, itemOperations={
 *     "get"={"method"="get"},
 *   })
 * @ORM\Entity
 */
class Schedule
{
    /**
     * @ORM\Id
     * @ORM\Column(type="date_string")
     * @ORM\GeneratedValue(strategy="NONE")
     * @Groups({"schedule"})
     * @ApiProperty(identifier=true)
     */
    private $date;

    /**
     * @var MenuItem
     *
     * @ORM\OneToMany(targetEntity="ScheduleItem", mappedBy="schedule", cascade={"persist"})
     * @ORM\OrderBy({"position" = "ASC"})
     * @Groups({"schedule", "schedule_item"})
     */
    private $items;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     * @Groups({"schedule"})
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     * @Groups({"schedule"})
     */
    private $updatedAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId()
    {
        return $this->date;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function setDate(\DateTime $date)
    {
        $this->date = $date->format('Y-m-d');

        return $this;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }

    public function addItem(ScheduleItem $item)
    {
        $item->setSchedule($this);

        $this->items->add($item);

        return $this;
    }
}
