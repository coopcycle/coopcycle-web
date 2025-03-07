<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Sylius\Payment;
use Gedmo\Timestampable\Traits\Timestampable;

class Refund
{
    use Timestampable;

    const LIABLE_PARTY_PLATFORM = 'platform';
    const LIABLE_PARTY_MERCHANT = 'merchant';

    private $id;
    private $payment;
    private $liableParty;
    private $amount;
    private $comments = '';
    private array $data = [];

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
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @param mixed $payment
     *
     * @return self
     */
    public function setPayment($payment)
    {
        $this->payment = $payment;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLiableParty()
    {
        return $this->liableParty;
    }

    /**
     * @param mixed $liableParty
     *
     * @return self
     */
    public function setLiableParty($liableParty)
    {
        $this->liableParty = $liableParty;

        return $this;
    }

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     *
     * @return self
     */
    public function setAmount(int $amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $data
     *
     * @return self
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * @param mixed $comments
     *
     * @return self
     */
    public function setComments($comments)
    {
        $this->comments = $comments;

        return $this;
    }
}
