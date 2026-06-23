<?php

namespace AppBundle\Messenger;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Pre-renders TemplatedEmail bodies before they are serialized into the async queue.
 *
 * Without this, TemplatedEmail subclasses (e.g. NucleosProfileBundle's RegistrationMail)
 * that store Doctrine entities in typed properties fail in the worker: the entity cannot
 * survive serialization, so the property is uninitialized when BodyRenderer tries to
 * call getContext() on the deserialized object.
 *
 * After rendering we clear htmlTemplate/textTemplate so the worker's BodyRenderer
 * sees no template to render and skips the email entirely.
 */
class RenderEmailMiddleware implements MiddlewareInterface
{
    public function __construct(private BodyRendererInterface $bodyRenderer) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // ReceivedStamp is present when a message comes back from a transport (worker).
        // Skip in that case — the body is already rendered.
        if ($envelope->last(ReceivedStamp::class) === null) {
            $message = $envelope->getMessage();
            if ($message instanceof SendEmailMessage) {
                $email = $message->getMessage();
                if ($email instanceof TemplatedEmail) {
                    $this->bodyRenderer->render($email);
                    $email->htmlTemplate(null)->textTemplate(null);
                }
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
