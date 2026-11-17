<?php

namespace AppBundle\Command;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Entity\GiftCard\GiftCardTransactionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Proves the gift card books still add up.
 *
 * Each card carries a cached balance so the common read doesn't have to sum
 * its ledger every time, and a cache is a thing that can be wrong. If one
 * ever disagrees with its ledger, the ledger is right and somebody needs to
 * know before a customer discovers it at a checkout.
 *
 * Also checks that no charge has had more transferred out of it than it took
 * in -- the local mirror of the rule Stripe enforces on `source_transaction`,
 * so a drift shows up here rather than as a refused transfer with a
 * restaurant waiting to be paid.
 *
 * Exits non-zero on any discrepancy, so it can be run from cron or CI and
 * actually be noticed.
 */
final class CheckGiftCardsCommand extends Command
{
    protected static $defaultName = 'coopcycle:gift-cards:check';

    public function __construct(
        private readonly GiftCardRepository $giftCardRepository,
        private readonly GiftCardTransactionRepository $transactionRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Checks that every gift card balance matches its ledger');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $checked = 0;
        $problems = 0;

        foreach ($this->giftCardRepository->createQueryBuilder('g')->getQuery()->toIterable() as $giftCard) {
            /** @var GiftCard $giftCard */
            $checked++;

            $ledger = $this->transactionRepository->getLedgerBalance($giftCard);

            if ($ledger !== $giftCard->getBalance()) {
                $problems++;
                $output->writeln(sprintf(
                    '<error>%s | balance %d, ledger %d</error>',
                    $giftCard->getCode(),
                    $giftCard->getBalance(),
                    $ledger
                ));
            }

            if ($giftCard->getBalance() < 0) {
                $problems++;
                $output->writeln(sprintf('<error>%s | negative balance %d</error>',
                    $giftCard->getCode(), $giftCard->getBalance()));
            }

            foreach ($giftCard->getPurchaseCharges() as $charge) {
                if ($charge->getAmountAllocated() + $charge->getAmountRefunded() > $charge->getAmount()) {
                    $problems++;
                    $output->writeln(sprintf(
                        '<error>%s | charge %s over-allocated: %d + %d > %d</error>',
                        $giftCard->getCode(),
                        $charge->getStripeChargeId(),
                        $charge->getAmountAllocated(),
                        $charge->getAmountRefunded(),
                        $charge->getAmount()
                    ));
                }
            }
        }

        $output->writeln(sprintf('Checked %d gift card(s), found %d problem(s)', $checked, $problems));

        return $problems > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
