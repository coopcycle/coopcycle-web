<?php

namespace AppBundle\Entity;

use Gedmo\Timestampable\Traits\Timestampable;

class DeliveryFormSubmission
{
    use Timestampable;

    private $id;
    private $deliveryForm;
    private $data;
    private $price;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getDeliveryForm()
    {
        return $this->deliveryForm;
    }

    /**
     * @param mixed $deliveryForm
     *
     * @return self
     */
    public function setDeliveryForm($deliveryForm)
    {
        $this->deliveryForm = $deliveryForm;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     *
     * @return self
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param mixed $price
     *
     * @return self
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }
}
