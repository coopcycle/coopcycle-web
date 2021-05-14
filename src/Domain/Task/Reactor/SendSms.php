<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Entity\Task;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Message\Sms;
use AppBundle\Service\SettingsManager;
use Hashids\Hashids;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendSms
{
    private $settingsManager;
    private $messageBus;

    public function __construct(
        SettingsManager $settingsManager,
        OrderRepository $orderRepository,
        MessageBusInterface $messageBus,
        PhoneNumberUtil $phoneNumberUtil,
        UrlGeneratorInterface $urlGenerator,
        TranslatorInterface $translator,
        string $secret)
    {
        $this->settingsManager = $settingsManager;
        $this->orderRepository = $orderRepository;
        $this->messageBus = $messageBus;
        $this->phoneNumberUtil = $phoneNumberUtil;
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
        $this->secret = $secret;
    }

    public function __invoke(TaskStarted $event)
    {
        if (!$this->settingsManager->canSendSms()) {
            return;
        }

        $task = $event->getTask();

        // Skip if this is related to foodtech
        if ($order = $this->orderRepository->findOneByTask($task)) {
            if ($order->hasVendor()) {
                return;
            }
        }

        $telephone = $task->getAddress()->getTelephone();

        if (!$telephone) {
            return;
        }

        $telephone = $this->phoneNumberUtil->format($telephone, PhoneNumberFormat::E164);

        $delivery = $task->getDelivery();

        if (null !== $delivery) {

            $hashids = new Hashids($this->secret, 8);

            $trackingUrl = $this->urlGenerator->generate('public_delivery', [
                'hashid' => $hashids->encode($delivery->getId())
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $text = $this->translator->trans('sms.with_tracking', [
                '%address%' => $task->getAddress()->getStreetAddress(),
                '%link%' => $trackingUrl
            ]);

        } else {
            $text = $this->translator->trans('sms.simple', [
                '%address%' => $task->getAddress()->getStreetAddress()
            ]);
        }

        $this->messageBus->dispatch(
            new Sms($text, $telephone)
        );
    }
}
