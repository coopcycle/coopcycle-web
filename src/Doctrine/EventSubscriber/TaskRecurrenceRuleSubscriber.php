<?php

namespace AppBundle\Doctrine\EventSubscriber;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Service\Geocoder;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class TaskRecurrenceRuleSubscriber implements EventSubscriber
{
    private $createdAddresses;

    public function __construct(
        Geocoder $geocoder,
        IriConverterInterface $iriConverter,
        PropertyAccessorInterface $propertyAccessor)
    {
        $this->geocoder = $geocoder;
        $this->iriConverter = $iriConverter;
        $this->propertyAccessor = $propertyAccessor;
        $this->createdAddresses = new \SplObjectStorage();
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $this->createdAddresses = new \SplObjectStorage();

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $isRecurrenceRule = fn ($entity) => $entity instanceof RecurrenceRule;

        $objects = array_merge(
            array_filter($uow->getScheduledEntityInsertions(), $isRecurrenceRule),
            array_filter($uow->getScheduledEntityUpdates(), $isRecurrenceRule)
        );

        if (count($objects) === 0) {
            return;
        }

        foreach ($objects as $object) {

            $template = $object->getTemplate();

            if ($template['@type'] === 'Task') {
                $address = $template['address'];

                $addressObj = null;
                if (is_string($address)) {
                    $addressObj = $this->iriConverter->getItemFromIri($address);
                } elseif (!isset($address['@id'])) {
                    $addressObj = $this->geocoder->geocode($address['streetAddress']);
                    $em->persist($addressObj);
                }

                if ($addressObj) {
                    $this->createdAddresses[$addressObj] = [
                        'object' => $object,
                        'path' => '[address]',
                    ];
                }

            } else {
                foreach ($template['hydra:member'] as $i => $task) {

                    $addressObj = null;
                    if (is_string($task['address'])) {
                        $addressObj = $this->iriConverter->getItemFromIri($task['address']);
                    } elseif (!isset($task['address']['@id'])) {
                        $addressObj = $this->geocoder->geocode($task['address']['streetAddress']);
                        $em->persist($addressObj);
                    }

                    if ($addressObj) {
                        $this->createdAddresses[$addressObj] = [
                            'object' => $object,
                            'path' => sprintf('[hydra:member][%d][address]', $i)
                        ];
                    }
                }
            }
        }

        if (count($this->createdAddresses) === 0) {
            return;
        }

        $uow->computeChangeSets();
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->createdAddresses) === 0) {
            return;
        }

        $em = $args->getEntityManager();

        foreach ($this->createdAddresses as $address) {

            $item = $this->createdAddresses[$address];

            $template = $item['object']->getTemplate();

            $this->propertyAccessor->setValue($template, $item['path'], [
                '@id' => $this->iriConverter->getIriFromItem($address),
                'streetAddress' => $address->getStreetAddress(),
            ]);

            $item['object']->setTemplate($template);
        }

        $em->flush();
    }
}
