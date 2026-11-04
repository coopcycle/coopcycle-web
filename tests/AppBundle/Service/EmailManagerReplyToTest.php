<?php

namespace Tests\AppBundle\Service;

use AppBundle\LoopEat\Context as LoopeatContext;
use AppBundle\LoopEat\ContextInitializer as LoopeatContextInitializer;
use AppBundle\Service\EmailManager;
use AppBundle\Service\EmailTemplateManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Twig\LocalBusinessRuntime;
use AppBundle\Twig\OrderRuntime;
use Hashids\Hashids;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class EmailManagerReplyToTest extends TestCase
{
    use ProphecyTrait;

    private function createEmailManager(array $settings): EmailManager
    {
        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager->get(Argument::any())->will(fn ($args) => $settings[$args[0]] ?? null);

        return new EmailManager(
            $this->prophesize(MailerInterface::class)->reveal(),
            $this->prophesize(TwigEnvironment::class)->reveal(),
            $this->prophesize(RendererInterface::class)->reveal(),
            $this->prophesize(TranslatorInterface::class)->reveal(),
            $settingsManager->reveal(),
            $this->prophesize(LoopeatContextInitializer::class)->reveal(),
            $this->prophesize(LoopeatContext::class)->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal(),
            'noreply@coopcycle.org',
            $this->prophesize(EmailTemplateManager::class)->reveal(),
            $this->prophesize(UrlGeneratorInterface::class)->reveal(),
            $this->prophesize(Hashids::class)->reveal(),
            $this->prophesize(RequestStack::class)->reveal(),
            $this->prophesize(Hashids::class)->reveal(),
            $this->prophesize(OrderRuntime::class)->reveal(),
            $this->prophesize(LocalBusinessRuntime::class)->reveal()
        );
    }

    public function testReplyToUsesTheAdministratorEmailWhenConfigured(): void
    {
        $message = $this
            ->createEmailManager([
                'brand_name' => 'CoopCycle',
                'administrator_email' => 'admin@coopcycle.org',
            ])
            ->createHtmlMessageWithReplyTo('Subject', '<p>Body</p>');

        $replyTo = $message->getReplyTo();

        $this->assertCount(1, $replyTo);
        $this->assertSame('admin@coopcycle.org', $replyTo[0]->getAddress());
        $this->assertSame('CoopCycle', $replyTo[0]->getName());
    }

    /**
     * Regression test: administrator_email is an optional setting, so an
     * instance that has never saved the settings form has none. Building an
     * Address from an empty string throws RfcComplianceException, which used to
     * 500 every page that sends a transactional email (the referral invite
     * form, order confirmations, ...). The header must simply be omitted.
     */
    public function testReplyToIsOmittedWhenTheAdministratorEmailIsNotConfigured(): void
    {
        $message = $this
            ->createEmailManager(['brand_name' => 'CoopCycle'])
            ->createHtmlMessageWithReplyTo('Subject', '<p>Body</p>');

        $this->assertCount(0, $message->getReplyTo());
        // The message is still sendable: From falls back to the transactional
        // address, which is a container parameter rather than a DB setting.
        $this->assertCount(1, $message->getFrom());
        $this->assertSame('noreply@coopcycle.org', $message->getFrom()[0]->getAddress());
    }
}
