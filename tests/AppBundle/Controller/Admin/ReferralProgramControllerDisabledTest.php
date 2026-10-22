<?php

namespace Tests\AppBundle\Controller\Admin;

use AppBundle\Controller\Admin\ReferralProgramController;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Service\Referral\ReferralLevelResolver;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReferralProgramControllerDisabledTest extends TestCase
{
    use ProphecyTrait;

    public function testThrowsNotFoundWhenFeatureDisabled(): void
    {
        $controller = new ReferralProgramController(
            false,
            $this->prophesize(SettingsManager::class)->reveal(),
            $this->prophesize(ReferralRepository::class)->reveal(),
            $this->prophesize(ReferralLevelResolver::class)->reveal(),
            $this->prophesize(EntityManagerInterface::class)->reveal(),
        );

        $this->expectException(NotFoundHttpException::class);

        $controller(Request::create('/admin/referral-program'), $this->prophesize(PaginatorInterface::class)->reveal());
    }
}
