<?php

namespace Tests\AppBundle\Service;

use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\Translation\TranslatorInterface;

class EmailManagerTest extends TestCase
{
    public function setUp()
    {
        $this->mailer = $this->prophesize(\Swift_Mailer::class);
        $this->twig = $this->prophesize(TwigEngine::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->settingsManager = $this->prophesize(SettingsManager::class);

        $this->emailManager = new EmailManager(
            $this->mailer->reveal(),
            $this->twig->reveal(),
            $this->translator->reveal(),
            $this->settingsManager->reveal(),
            'transactional@coopcycle.org'
        );
    }

    public function testCreateMessage()
    {
        $this->settingsManager
            ->get('brand_name')
            ->willReturn('Acme');

        $message = $this->emailManager->createHtmlMessage();

        $this->assertEquals(['transactional@coopcycle.org' => 'Acme'], $message->getFrom());
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

        $this->mailer->send($message)->shouldBeCalled();

        $message->setTo('joe@example.com', 'Joe');
        $this->emailManager->send($message);
    }
}
