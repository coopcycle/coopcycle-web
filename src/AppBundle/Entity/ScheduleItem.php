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
 * @ApiResource(shortName="ScheduleItem", attributes={
 *   "denormalization_context"={"groups"={"schedule_item"}},
 *   "normalization_context"={"groups"={"schedule", "schedule_item", "delivery", "place"}}
 * }, collectionOperations={
 *   "get"={"method"="get"},
 *   "my_schedule" = {
 *     "route_name" = "my_schedule",
 *     "swagger_context" = {
 *       "parameters" = {{
 *         "name" = "date",
 *         "in" = "path",
 *         "required" = "true",
 *         "type" = "string"
 *       }}
 *     }
 *   }
 * }, itemOperations={
 *   "get"={"method"="get"},
 *   "schedule_item_done"={"route_name"="api_schedule_items_put_done"}
 * })
 * @ORM\Entity
 * @ORM\Table(name="schedule_item", uniqueConstraints={
 *   @ORM\UniqueConstraint(name="schedule_item_unique",columns={"schedule_date", "courier_id", "delivery_id", "address_id"})
 * })
 */
class ScheduleItem
{
    const STATUS_TODO = 'TODO';
    const STATUS_DONE = 'DONE';

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @ORM\Column(name="schedule_date", type="date_string")
     * @Groups({"schedule"})
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="Schedule", inversedBy="items", cascade={"persist"})
     * @ORM\JoinColumn(name="schedule_date", referencedColumnName="date", nullable=false)
     */
    private $schedule;

    /**
     * @ORM\ManyToOne(targetEntity="ApiUser")
     * @Groups({"schedule", "schedule_item"})
     */
    private $courier;

    /**
     * @ORM\ManyToOne(targetEntity="Delivery")
     * @Groups({"schedule", "schedule_item"})
     */
    private $delivery;

    /**
     * @ORM\ManyToOne(targetEntity="Address")
     * @Groups({"schedule", "schedule_item"})
     */
    private $address;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"schedule_item"})
     */
    private $position;

    /**
     * @ORM\Column(type="string")
     * @Groups({"schedule_item"})
     */
    private $status = self::STATUS_TODO;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updatedAt;

    public function getId()
    {
        return $this->id;
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

    public function getDate()
    {
        return $this->date;
    }

    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }

    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    public function getSchedule()
    {
        return $this->schedule;
    }

    public function setSchedule($schedule)
    {
        $this->schedule = $schedule;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }
}
