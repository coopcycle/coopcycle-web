<?php

namespace AppBundle\Command;

use Sylius\Component\Promotion\Generator\PromotionCouponGeneratorInstruction;
use Sylius\Component\Promotion\Generator\PromotionCouponGeneratorInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Repository\PromotionRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Drop-in replacement for Sylius' generate-coupons command, adding
 * --prefix / --suffix options. The vendor command is final, so we reimplement
 * it rather than subclass.
 *
 * Prefix and suffix are already supported end to end (the instruction carries
 * them, the generator wraps the random part, and the generation policy scopes
 * its capacity check by them); only the CLI surface was missing.
 *
 * @see \Sylius\Bundle\PromotionBundle\Console\Command\GenerateCouponsCommand
 */
final class GenerateCouponsCommand extends Command
{
    protected static $defaultName = 'sylius:promotion:generate-coupons';

    /** @param PromotionRepositoryInterface<PromotionInterface> $promotionRepository */
    public function __construct(
        private PromotionRepositoryInterface $promotionRepository,
        private PromotionCouponGeneratorInterface $couponGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generates coupons for a given promotion')
            ->addArgument('promotion-code', InputArgument::REQUIRED, 'Code of the promotion')
            ->addArgument('count', InputArgument::REQUIRED, 'Amount of coupons to generate')
            ->addOption('length', 'len', InputOption::VALUE_OPTIONAL, 'Length of the coupon code (default 10)', '10')
            ->addOption('prefix', null, InputOption::VALUE_OPTIONAL, 'String prepended to every coupon code')
            ->addOption('suffix', null, InputOption::VALUE_OPTIONAL, 'String appended to every coupon code')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $promotionCode */
        $promotionCode = $input->getArgument('promotion-code');

        /** @var PromotionInterface|null $promotion */
        $promotion = $this->promotionRepository->findOneBy(['code' => $promotionCode]);

        if ($promotion === null) {
            $output->writeln('<error>No promotion found with this code</error>');

            return 1;
        }

        if (!$promotion->isCouponBased()) {
            $output->writeln('<error>This promotion is not coupon based</error>');

            return 1;
        }

        $instruction = new PromotionCouponGeneratorInstruction(
            amount: (int) $input->getArgument('count'),
            prefix: $input->getOption('prefix'),
            codeLength: (int) $input->getOption('length'),
            suffix: $input->getOption('suffix'),
        );

        try {
            $this->couponGenerator->generate($promotion, $instruction);
        } catch (\Exception $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return 1;
        }

        $output->writeln('<info>Coupons have been generated</info>');

        return 0;
    }
}
