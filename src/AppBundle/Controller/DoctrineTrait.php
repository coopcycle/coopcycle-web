<?php

namespace AppBundle\Controller;

trait DoctrineTrait
{
    private function getManager($entityName)
    {
        return $this->getDoctrine()->getManagerForClass('AppBundle\\Entity\\'.$entityName);
    }

    private function getRepository($entityName)
    {
        return $this->getManager($entityName)->getRepository('AppBundle\\Entity\\'.$entityName);
    }
}