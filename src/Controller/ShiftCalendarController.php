<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Service\Shift\CalendarFeed;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ShiftCalendarController extends AbstractController
{
    /**
     * Public (tokenized) endpoint: calendar apps poll it without any session,
     * the HMAC token in the URL is the authentication. Invalid tokens get a
     * 404 rather than a 403, to not confirm the user id exists.
     */
    #[Route(path: '/calendar/shifts/{id}/{token}/shifts.ics', name: 'shift_calendar_feed', requirements: ['id' => '\d+', 'token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function feedAction(int $id, string $token, EntityManagerInterface $entityManager, CalendarFeed $calendarFeed): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (null === $user || !$user->isEnabled() || !$calendarFeed->isTokenValid($user, $token)) {
            throw $this->createNotFoundException();
        }

        return new Response($calendarFeed->render($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="shifts.ics"',
        ]);
    }
}
