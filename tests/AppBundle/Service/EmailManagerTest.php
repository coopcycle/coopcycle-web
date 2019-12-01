<?php

namespace Tests\AppBundle\Service;

use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class EmailManagerTest extends TestCase
{
    public function setUp(): void
    {
        $this->mailer = $this->prophesize(\Swift_Mailer::class);
        $this->twig = $this->prophesize(TwigEnvironment::class);
        $this->mjml = $this->prophesize(RendererInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);

        $this->emailManager = new EmailManager(
            $this->mailer->reveal(),
            $this->twig->reveal(),
            $this->mjml->reveal(),
            $this->translator->reveal(),
            $this->settingsManager->reveal(),
            'transactional@coopcycle.org'
        );
    }

    public function testCreateHtmlMessage()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $message = $this->emailManager->createHtmlMessage();

        $this->assertEquals(['transactional@coopcycle.org' => 'Acme'], $message->getFrom());
        $this->assertEquals(['transactional@coopcycle.org' => 'Acme'], $message->getSender());
        $this->assertNull($message->getReplyTo());
    }

    public function testCreateHtmlMessageWithReplyTo()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $this->settingsManager
            ->get('administrator_email')
            ->willReturn('admin@acme.com');

        $message = $this->emailManager->createHtmlMessageWithReplyTo();

        $this->assertEquals(['transactional@coopcycle.org' => 'Acme'], $message->getFrom());
        $this->assertEquals(['transactional@coopcycle.org' => 'Acme'], $message->getSender());
        $this->assertEquals(['admin@acme.com' => 'Acme'], $message->getReplyTo());
    }

    public function testMessageIsNotSentToDemoUser()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $message = $this->emailManager->createHtmlMessage();

        $this->mailer->send($message)->shouldNotBeCalled();

        $message->setTo('joe@demo.coopcycle.org');
        $this->emailManager->send($message);

        $message->setTo('joe@demo.coopcycle.org', 'Joe');
        $this->emailManager->send($message);
    }

    public function testMessageIsSentToNonDemoUser()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $message = $this->emailManager->createHtmlMessage();

        $this->mailer->send($message)->shouldBeCalled();

        $message->setTo('joe@example.com', 'Joe');
        $this->emailManager->send($message);
    }
}
