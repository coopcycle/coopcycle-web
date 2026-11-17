<?php

namespace AppBundle\Command;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Entity\GiftCard\GiftCardTransaction;
use AppBundle\Service\GiftCard\GiftCardStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Forfeits the balance of gift cards whose expiry date has passed.
 *
 * Does nothing at all unless the instance has deliberately opted into an
 * expiry policy, and that default is not timidity: several EU countries
 * restrict how short a voucher's life may be, and a number of US states
 * require unclaimed balances to be handed to the state rather than kept by
 * the seller. "Never expires" is the only answer that is safe everywhere
 * without advice, so it is the one that ships.
 *
 * Unlike the loyalty points sweep, this one really does take money: an
 * expired card's balance is gone. Hence --dry-run, and hence the ledger row
 * written for every forfeit, so a customer asking where their balance went
 * can be given an answer.
 */
final class ExpireGiftCardsCommand extends Command
{
    protected static $defaultName = 'coopcycle:gift-cards:expire';

    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly GiftCardRepository $giftCardRepository,
        private readonly GiftCardStatus $giftCardStatus,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Forfeits the balance of gift cards whose expiry date has passed')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would expire, without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = $input->getOption('dry-run');

        if (GiftCardStatus::EXPIRY_POLICY_NEVER === $this->giftCardStatus->getExpiryPolicy()) {
            $output->writeln('Gift cards do not expire on this instance, nothing to do.');

            return Command::SUCCESS;
        }

        $query = $this->giftCardRepository->createLapsedQueryBuilder()->getQuery();

        $cards = 0;
        $amount = 0;

        foreach ($query->toIterable() as $giftCard) {
            /** @var GiftCard $giftCard */
            $balance = $giftCard->getBalance();

            $cards++;
            $amount += $balance;

            $output->writeln(sprintf(
                '%s | %s | forfeiting %d',
                $dryRun ? '[dry-run]' : 'Expiring',
                $giftCard->getCode(),
                $balance
            ));

            if ($dryRun) {
                continue;
            }

            $this->entityManager->persist(GiftCardTransaction::expiry($giftCard, $balance));
            $giftCard->expire();

            if (0 === $cards % self::BATCH_SIZE) {
                $this->entityManager->flush();
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $output->writeln(sprintf(
            '%s %d gift card(s), %d forfeited',
            $dryRun ? 'Would expire' : 'Expired',
            $cards,
            $amount
        ));

        return Command::SUCCESS;
    }
}
