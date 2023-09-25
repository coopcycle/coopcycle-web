<?php

namespace AppBundle\Form;

use AppBundle\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationsType extends AbstractType
{
    private $translator;
    private $entityManager;

    public function __construct(
        TranslatorInterface $translator,
        EntityManagerInterface $entityManager)
    {
        $this->translator = $translator;
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $notifications = $this->entityManager
            ->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->orderBy('n.id')
            ->getQuery()
            ->getResult();

        foreach($notifications as $notification) {
            $builder->add($notification->getName(), CheckboxType::class, [
                'label' => $this->translator->trans(sprintf('form.settings.notifications.%s', $notification->getName())),
                'translation_domain' => 'messages',
                'required'   => false,
                'data' => $notification->getEnabled()
            ]);
        }
    }

}
