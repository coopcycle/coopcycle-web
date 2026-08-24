<?php

namespace AppBundle\Service;

use AppBundle\Entity\SchedulePublication;
use AppBundle\Entity\SchedulePublicationRepository;
use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class SchedulePublisher
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailManager $emailManager,
        private readonly TwigEnvironment $twig,
        private readonly RendererInterface $mjml,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly LoggerInterface $logger)
    {}

    /**
     * Publishes the schedule of the week starting at $weekStart (a Monday):
     * from then on couriers can see and apply to the week's shifts. All
     * planning users are notified by email.
     *
     * @throws BadRequestHttpException when the week is already published
     */
    public function publish(\DateTimeImmutable $weekStart): SchedulePublication
    {
        /** @var SchedulePublicationRepository $repository */
        $repository = $this->entityManager->getRepository(SchedulePublication::class);

        if (null !== $repository->findOneByWeekStart($weekStart)) {
            throw new BadRequestHttpException('This week is already published');
        }

        $publication = new SchedulePublication();
        $publication->setWeekStart(\DateTime::createFromImmutable($weekStart));

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $publication->setPublishedBy($user);
        }

        $this->entityManager->persist($publication);
        $this->entityManager->flush();

        $this->notifyByEmail($weekStart);

        return $publication;
    }

    private function notifyByEmail(\DateTimeImmutable $weekStart): void
    {
        try {
            $subject = $this->translator->trans('shift_week.published.subject', [
                '%date%' => $weekStart->format('d/m/Y'),
            ], 'emails');

            $body = $this->mjml->render($this->twig->render('emails/shift_week_published.mjml.twig', [
                'weekStart' => $weekStart,
            ]));

            foreach ($this->findPlanningUsers() as $user) {
                $message = $this->emailManager->createHtmlMessage($subject, $body);
                $this->emailManager->sendTo($message, $user->getEmail());
            }
        } catch (\Exception $e) {
            // The publication itself must not fail because of notifications
            $this->logger->error(sprintf('Could not send schedule published emails: %s', $e->getMessage()));
        }
    }

    /**
     * The same audience as the planning grid: users with a role
     * literally present in their roles column (hierarchy not expanded).
     *
     * @return User[]
     */
    private function findPlanningUsers(): array
    {
        $qb = $this->entityManager->getRepository(User::class)->createQueryBuilder('u');

        $rolesClause = $qb->expr()->orX();
        foreach (['ROLE_COURIER', 'ROLE_DISPATCHER', 'ROLE_ADMIN'] as $role) {
            $rolesClause->add($qb->expr()->like('u.roles', $qb->expr()->literal('%'.$role.'%')));
        }

        return $qb
            ->andWhere($rolesClause)
            ->andWhere('u.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();
    }
}
