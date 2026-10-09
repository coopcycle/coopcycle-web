<?php

namespace Tests\AppBundle\EventListener;

use AppBundle\Entity\User;
use AppBundle\EventListener\RegistrationListener;
use Nucleos\ProfileBundle\Event\UserFormEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Nucleos\ProfileBundle\EventListener\EmailConfirmationListener is the only
 * vendor code that ever calls User::setEnabled(), and it's only registered
 * when email confirmation is enabled. When it's disabled, nothing else did
 * -- new web registrations were silently created disabled and could never
 * log in, bouncing to /login on every subsequent request.
 */
class RegistrationListenerTest extends TestCase
{
    use ProphecyTrait;

    private function buildEvent(User $user): UserFormEvent
    {
        $form = $this->prophesize(FormInterface::class);
        $form->has(\Prophecy\Argument::any())->willReturn(false);

        return new UserFormEvent($user, $form->reveal(), Request::create('/register/'));
    }

    public function testEnablesTheUserWhenConfirmationIsDisabled(): void
    {
        $listener = new RegistrationListener(false);

        $user = new User();
        self::assertFalse($user->isEnabled());

        $listener->onRegistrationSuccess($this->buildEvent($user));

        self::assertTrue($user->isEnabled());
    }

    public function testLeavesTheUserDisabledWhenConfirmationIsEnabled(): void
    {
        $listener = new RegistrationListener(true);

        $user = new User();

        $listener->onRegistrationSuccess($this->buildEvent($user));

        // Left to Nucleos\ProfileBundle\EventListener\EmailConfirmationListener,
        // which runs on the same event and explicitly disables the account
        // until the confirmation link is clicked.
        self::assertFalse($user->isEnabled());
    }
}
