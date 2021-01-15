<?php

namespace AppBundle\MessageHandler;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Message\OrderReceiptEmail;
use AppBundle\Service\EmailManager;
use AppBundle\Sylius\Order\ReceiptGenerator;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class OrderReceiptEmailHandler implements MessageHandlerInterface
{
    private EmailManager $emailManager;
    private EntityManagerInterface $entityManager;
    private ReceiptGenerator $generator;

    public function __construct(
        EmailManager $emailManager,
        EntityManagerInterface $entityManager,
        ReceiptGenerator $generator,
        Filesystem $receiptsFilesystem)
    {
        $this->emailManager = $emailManager;
        $this->entityManager = $entityManager;
        $this->generator = $generator;
        $this->receiptsFilesystem = $receiptsFilesystem;
    }

    public function __invoke(OrderReceiptEmail $message)
    {
        $order =
            $this->entityManager->getRepository(Order::class)->findOneByNumber($message->getNumber());

        if (!$order) {
            return;
        }

        if (!$order->hasReceipt()) {
            $order->setReceipt(
                $this->generator->create($order)
            );
            $this->entityManager->flush();
        }

        $filename = sprintf('%s.pdf', $order->getNumber());

        $this->generator->generate($order, $filename);

        $email = $this->emailManager->createOrderReceiptMessage($order);
        $email->attach(
            (string) $this->receiptsFilesystem->read($filename),
            $filename,
            'application/pdf'
        );

        $email->to(
            sprintf('%s <%s>', $order->getCustomer()->getFullName(), $order->getCustomer()->getEmail())
        );

        $this->emailManager->send($email);
    }
}
