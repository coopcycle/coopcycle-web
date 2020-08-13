<?php

namespace Tests\AppBundle\Form\Type;

use AppBundle\Entity\Sylius\TaxCategory;
use AppBundle\Entity\Sylius\TaxRate;
// use AppBundle\Entity\TimeSlot\Choice;
// use AppBundle\Form\Type\TimeSlotChoice;
use AppBundle\Form\Type\ProductTaxCategoryChoiceLoader;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\AbstractQuery;
// use AppBundle\Utils\TimeSlotChoiceWithDate;
// use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Product\Factory\ProductVariantFactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductTaxCategoryChoiceLoaderTest extends KernelTestCase
{
    use ProphecyTrait;

    // public function tearDown(): void
    // {
    //     Carbon::setTestNow();
    // }

    // private function assertTimeSlotChoice(\DateTime $start, \DateTime $end, TimeSlotChoice $choice)
    // {
    //     $datePeriod = $choice->toDatePeriod();

    //     $this->assertEquals($start, $datePeriod->start);
    //     $this->assertEquals($end, $datePeriod->end);
    // }

    public function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $this->taxCategoryRepository = $this->prophesize(EntityRepository::class);
        $this->taxRateResolver = $this->prophesize(TaxRateResolverInterface::class);
        $this->variantFactory = $this->prophesize(ProductVariantFactoryInterface::class);

        $this->queryBuilder = $this->prophesize(QueryBuilder::class);
        $this->expr = $this->prophesize(Expr::class);
        $this->query = $this->prophesize(AbstractQuery::class);

        $this->queryBuilder->expr()->willReturn($this->expr->reveal());
        $this->queryBuilder->getQuery()->willReturn($this->query->reveal());

        $this->taxCategoryRepository
            ->createQueryBuilder(Argument::type('string'))
            ->willReturn($this->queryBuilder->reveal())
            ;
    }

    public function testLegacyTaxes()
    {
        $choiceLoader = new ProductTaxCategoryChoiceLoader(
            $this->taxCategoryRepository->reveal(),
            $this->taxRateResolver->reveal(),
            $this->variantFactory->reveal(),
            'fr',
            true
        );

        $notServiceExpr = new Expr();
        $notNewExpr = new Expr();

        $this->expr
            ->notIn('c.code', [
                'SERVICE',
                'SERVICE_TAX_EXEMPT'
            ])
            ->willReturn($notServiceExpr)
            ->shouldBeCalled();

        $this->expr
            ->notIn('c.code', [
                'DRINK',
                'DRINK_ALCOHOL',
                'FOOD',
                'FOOD_TAKEAWAY',
                'JEWELRY',
                'BASE_STANDARD',
                'BASE_INTERMEDIARY',
                'BASE_REDUCED',
            ])
            ->willReturn($notNewExpr)
            ->shouldBeCalled();

        $this->queryBuilder
            ->andWhere($notServiceExpr)
            ->shouldBeCalled();

        $this->queryBuilder
            ->andWhere($notNewExpr)
            ->shouldBeCalled();

        $this->query->getResult()->willReturn([
            new TaxCategory(),
            new TaxCategory(),
        ]);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);
    }

    public function testNewTaxes()
    {
        $choiceLoader = new ProductTaxCategoryChoiceLoader(
            $this->taxCategoryRepository->reveal(),
            $this->taxRateResolver->reveal(),
            $this->variantFactory->reveal(),
            'fr',
            false
        );

        $notServiceExpr = new Expr();
        $notNewExpr = new Expr();

        $this->expr
            ->notIn('c.code', [
                'SERVICE',
                'SERVICE_TAX_EXEMPT'
            ])
            ->willReturn($notServiceExpr)
            ->shouldBeCalled();

        $this->expr
            ->in('c.code', [
                'DRINK',
                'DRINK_ALCOHOL',
                'FOOD',
                'FOOD_TAKEAWAY',
                'JEWELRY',
                'BASE_STANDARD',
                'BASE_INTERMEDIARY',
                'BASE_REDUCED',
            ])
            ->willReturn($notNewExpr)
            ->shouldBeCalled();

        $this->queryBuilder
            ->andWhere($notServiceExpr)
            ->shouldBeCalled();

        $this->queryBuilder
            ->andWhere($notNewExpr)
            ->shouldBeCalled();

        $pv1 = $this->prophesize(ProductVariantInterface::class);
        $pv2 = $this->prophesize(ProductVariantInterface::class);

        $this->variantFactory
            ->createNew()
            ->willReturn(
                $pv1,
                $pv2
            );

        $tc1 = new TaxCategory();
        $tc2 = new TaxCategory();

        $this->query->getResult()->willReturn([
            $tc1,
            $tc2,
        ]);

        $this->taxRateResolver
            ->resolve(Argument::type(ProductVariantInterface::class), [
                'country' => 'fr'
            ])
            ->willReturn(null);

        $choiceList = $choiceLoader->loadChoiceList();
        $choices = $choiceList->getChoices();

        $this->assertCount(2, $choices);
    }
}
