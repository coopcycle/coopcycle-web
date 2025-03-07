<?php

namespace AppBundle\Transporter;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Transporter\Interface\ReportGeneratorInterface;
use Transporter\TransporterImpl;
use Transporter\TransporterOptions;

class ReportFromCC {

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager
    ) { }

    public function generateReport(
        EDIFACTMessage $message,
        TransporterOptions $opts
    ): ReportGeneratorInterface {
        $impl = new TransporterImpl($opts->getTransporter());
        /** @var ReportGeneratorInterface $generator */
        $generator = new ($impl->reportGenerator)($opts);
        $generator->setDocID(strval($message->getId()));
        $generator->setReference($message->getReference());
        $generator->setReceipt($message->getReference());
        $pods = $this->attachedFiles($message);
        if (!empty($pods)) {
            $generator->setPods($pods);

            //Persist pods on the messages entity to keep valid logs
            $message->setPods($pods);
            $this->entityManager->persist($message);
        }
        if (!is_null($message->getAppointment())) {
            $generator->setAppointment($message->getAppointment());
        }
        $generator->setDSJ($message->getCreatedAt());
        [$situation, $reason] = explode('|', $message->getSubMessageType());
        $generator->setSituation(constant("Transporter\Enum\ReportSituation::$situation"));
        $generator->setReason(constant("Transporter\Enum\ReportReason::$reason"));
        return $generator;

    }
    /**
     * @param array<ReportGeneratorInterface> $reports
     */
    public function buildSCONTR(
        array $reports,
        TransporterOptions $opts
    ): string
    {
        $impl = new TransporterImpl($opts->getTransporter());
        $interchange = new ($impl->interchange)($opts);
       foreach ($reports as $report) {
            $interchange->addGenerator($report);
        }

        return $interchange->generate();
    }

    //FIXME: This is a bit hacky, i'd prefer to attach a listener on the Task entity.
    //       But due to an optimization TaskImage are send AFTER the task is created.
    //       So... this is what i come up with.
    private function attachedFiles(EDIFACTMessage $message): array
    {
        if ($message->getSubMessageType() !== 'LIV|CFM') {
            return $message->getPods();
        }
        $pods = $message->getTasks()->map(
            fn(Task $t) => $t->getImages()->map(
                fn(TaskImage $i) => $this->urlGenerator->generate(
                    'task_image_public',
                    ['path' => $i->getImageName()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            )->toArray()
        )->toArray();
        return array_unique(array_merge($message->getPods(), ...$pods));
    }
}
