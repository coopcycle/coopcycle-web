<?php

namespace AppBundle\Entity;

class Notification
{
    /**
     * @var string
     */
    private string $name;

    /**
     * @var bool
     */
    private bool $enabled;

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }
}
