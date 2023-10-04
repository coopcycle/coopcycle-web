<?php

namespace AppBundle\Form;

use AppBundle\Entity\Notification;
use AppBundle\Service\Notifications;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationsType extends AbstractType
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Notifications $notifications,
        private TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $events = $this->notifications->getConfigurableEvents();

        foreach ($events as $event) {
            $builder->add($event, CheckboxType::class, [
                'label' => $this->translator->trans(sprintf('form.settings.notifications.%s', $event)),
                'translation_domain' => 'messages',
                'required'   => false,
                'data' => $this->notifications->isEventEnabled($event),
            ]);
        }

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            foreach ($event->getData() as $name => $checked) {

                $notification = $this->entityManager
                    ->getRepository(Notification::class)
                    ->find($name);

                if (null === $notification) {
                    $notification = new Notification();
                    $notification->setName($name);
                    $this->entityManager->persist($notification);
                }

                $notification->setEnabled($checked);
            }

            $this->entityManager->flush();
        });
    }
}
