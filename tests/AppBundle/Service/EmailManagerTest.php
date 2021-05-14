<?php

namespace Tests\AppBundle\Service;

use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use NotFloran\MjmlBundle\Renderer\RendererInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;

class EmailManagerTest extends TestCase
{
    use ProphecyTrait;

    public function setUp(): void
    {
        $this->mailer = $this->prophesize(MailerInterface::class);
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

        $message = $this->emailManager->createHtmlMessage('Hello, world!');

        $from = $message->getFrom();
        $sender = $message->getSender();

        $this->assertIsArray($from);

        $this->assertEquals('"Acme" <transactional@coopcycle.org>', $from[0]->toString());
        $this->assertEquals('"Acme" <transactional@coopcycle.org>', $sender->toString());
        $this->assertEmpty($message->getReplyTo());
    }

    public function testCreateHtmlMessageWithReplyTo()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $this->settingsManager
            ->get('administrator_email')
            ->willReturn('admin@acme.com');

        $message = $this->emailManager->createHtmlMessageWithReplyTo('Hello, world!');

        $from = $message->getFrom();
        $sender = $message->getSender();
        $replyTo = $message->getReplyTo();

        $this->assertIsArray($from);
        $this->assertIsArray($replyTo);

        $this->assertEquals('"Acme" <transactional@coopcycle.org>', $from[0]->toString());
        $this->assertEquals('"Acme" <transactional@coopcycle.org>', $sender->toString());
        $this->assertEquals('"Acme" <admin@acme.com>', $replyTo[0]->toString());
    }

    public function testMessageIsNotSentToDemoUser()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $message = $this->emailManager->createHtmlMessage('Hello, world!');

        $this->mailer->send($message)->shouldNotBeCalled();

        $message->addTo('joe@demo.coopcycle.org');
        $this->emailManager->send($message);

        $message->addTo('Joe <joe@demo.coopcycle.org>');
        $this->emailManager->send($message);
    }

    public function testMessageIsSentToNonDemoUser()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $message = $this->emailManager->createHtmlMessage('Hello, world!');

        $this->mailer->send($message)->shouldBeCalled();

        $message->addTo('Joe <joe@example.com>');

        $this->emailManager->send($message);
    }

    public function testSendToWithString()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $message = $this->emailManager->createHtmlMessage('Hello, world!');

        $this->mailer->send($message)->shouldBeCalled();

        $this->emailManager->sendTo($message, 'Joe <joe@example.com>');

        $to = $message->getTo();

        $this->assertIsArray($to);
        $this->assertEquals('"Joe" <joe@example.com>', $to[0]->toString());
    }

    public function testSendToWithArray()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $message = $this->emailManager->createHtmlMessage('Hello, world!');

        $this->mailer->send($message)->shouldBeCalled();

        $this->emailManager->sendTo($message, ...['Joe <joe@example.com>', 'jane@example.com']);

        $to = $message->getTo();

        $this->assertIsArray($to);
        $this->assertEquals('"Joe" <joe@example.com>', $to[0]->toString());
        $this->assertEquals('jane@example.com', $to[1]->toString());
    }
}
